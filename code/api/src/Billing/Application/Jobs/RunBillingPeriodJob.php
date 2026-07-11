<?php

declare(strict_types=1);

namespace Urbania\Billing\Application\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Throwable;
use Urbania\Billing\Application\Support\Decimal;
use Urbania\Billing\Infrastructure\Models\EloquentBillingPeriod;
use Urbania\Billing\Infrastructure\Models\EloquentBillingRun;
use Urbania\Billing\Infrastructure\Models\EloquentChargeConcept;
use Urbania\Billing\Infrastructure\Models\EloquentInvoice;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyCoefficient;

/**
 * Corrida de facturación asíncrona (R-COB-22, COBRANZA-B03).
 *
 * Despachada por `POST /billing-periods/{id}/billing-runs`, que responde `202` de
 * inmediato con el `billing_run` en `en_proceso`. Este Job hace el prorrateo real y
 * deja el run en `completado` (o `fallido`), poblando `resumen` (decisión 8 del
 * PANORAMA) para que el cliente que hace polling sobre `GET /billing-runs/{id}` pueda
 * explicar por qué el conteo final no coincide con el esperado.
 *
 * Lectura cross-context (solo lectura, mismo scope de tenant) de `Properties`
 * (`EloquentProperty`, `EloquentPropertyCoefficient`) — ver
 * `shared/adr/ADR-002-lectura-cross-context-modulo-monolito.md`. Nunca escribe sobre
 * modelos de otro bounded context.
 *
 * ## Garantía de no-duplicación (verify-council de COBRANZA-B03)
 *
 * El council encontró tres rutas por las que este Job podía facturar dos veces el mismo
 * periodo. Las tres nacían de tratar `billing_runs.estado` como si fuera un guard
 * confiable, cuando se escribía FUERA de la transacción que commitea las facturas:
 *
 * 1. Dos POST concurrentes pasaban el check-then-act del controller.
 * 2. Un fallo entre el commit de las facturas y el `save()` del estado dejaba el run en
 *    `fallido` con las facturas ya escritas — y un run `fallido` no bloquea uno nuevo,
 *    así que el operador redisparaba y duplicaba. Sin concurrencia alguna.
 * 3. Un worker muerto tras el commit hacía que la cola redelivere el job; el guard veía
 *    el run todavía en `en_proceso` y volvía a prorratear entero.
 *
 * Ahora: **todo el prorrateo, la transición de estado y el `resumen` viven dentro de una
 * única transacción**, con un re-chequeo bajo `lockForUpdate` al entrar. Y la invariante
 * real vive en la BD (`invoices_period_property_unique`: una unidad, una factura por
 * periodo), que revierte cualquier segundo escritor sin importar por qué ruta llegó.
 * `fallido` vuelve a significar lo que el operador cree que significa: no se escribió
 * nada.
 */
final class RunBillingPeriodJob implements ShouldQueue
{
    use Queueable;

    /**
     * Un solo intento: reintentar automáticamente una corrida a medio camino podría
     * duplicar facturas. La tarjeta es explícita — sin reintento automático en Fase 1;
     * el usuario dispara una corrida nueva si esta falla.
     */
    public int $tries = 1;

    /**
     * Menor que el `retry_after` de la cola (90s, ver config/queue.php) para que un job
     * lento muera por timeout ANTES de que la cola lo redelivere — si no, se solapan dos
     * ejecuciones del mismo prorrateo.
     */
    public int $timeout = 60;

    public function __construct(
        private readonly string $billingRunId,
    ) {}

    public function handle(): void
    {
        $run = EloquentBillingRun::query()->find($this->billingRunId);

        if ($run === null || $run->estado !== 'en_proceso') {
            return;
        }

        try {
            DB::transaction(function () use ($run): void {
                // Re-verificación bajo lock, YA dentro de la transacción: si otra corrida
                // completó este periodo mientras este job esperaba en la cola (o si la cola
                // redelivera este mismo job tras un worker muerto), se sale sin escribir
                // nada en vez de prorratear 300 facturas para después revertirlas.
                $period = EloquentBillingPeriod::query()
                    ->whereKey($run->billing_period_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $yaFacturado = EloquentBillingRun::query()
                    ->where('billing_period_id', $period->id)
                    ->where('id', '!=', $run->id)
                    ->where('estado', 'completado')
                    ->exists();

                if ($yaFacturado) {
                    throw new \RuntimeException('El periodo ya fue facturado por otra corrida completada.');
                }

                $resumen = $this->prorratear($run, $period);

                // DENTRO de la transacción: si esto viola `billing_runs_completado_unique`,
                // las facturas recién creadas se revierten con él.
                $run->estado = 'completado';
                $run->resumen = $resumen;
                $run->save();
            });
        } catch (Throwable $e) {
            $this->marcarFallido($run, $e);
        }
    }

    /**
     * Lo invoca la cola cuando el job agota sus intentos o muere por timeout/OOM —
     * casos en los que `handle()` nunca llega a su `catch`. Sin este hook, un worker
     * matado dejaba el run en `en_proceso` para siempre y, como el controller rechaza
     * toda corrida nueva mientras exista una `en_proceso`, el periodo quedaba
     * imposible de facturar sin un UPDATE manual en producción.
     */
    public function failed(?Throwable $e): void
    {
        $run = EloquentBillingRun::query()->find($this->billingRunId);

        if ($run === null || $run->estado !== 'en_proceso') {
            return;
        }

        $this->marcarFallido($run, $e);
    }

    /**
     * Marca la corrida como fallida sin filtrar internals por API.
     *
     * `resumen` se devuelve tal cual en `GET /billing-runs/{id}`, así que no puede
     * llevar el mensaje crudo de la excepción: un `QueryException` de PDO incluye el SQL
     * completo CON los bindings (valores monetarios reales) y los nombres de tablas y
     * columnas. Se persiste solo un código estable y un `trace_id` correlacionable con
     * el log; el detalle va a `report()`.
     */
    private function marcarFallido(EloquentBillingRun $run, ?Throwable $e): void
    {
        if ($e !== null) {
            report($e);
        }

        $traceId = (string) Str::orderedUuid();

        try {
            // El modelo puede haber quedado sucio con `estado = completado` de una closure
            // abortada — se relee para no arrastrar atributos de una transacción que no
            // llegó a existir.
            $run->refresh();

            $run->estado = 'fallido';
            $run->resumen = [
                'unidades_facturadas' => 0,
                'unidades_omitidas' => 0,
                'detalle_omitidas' => [],
                'conceptos_omitidos' => [],
                'error' => [
                    'code' => 'BILLING_RUN_FAILED',
                    'trace_id' => $traceId,
                ],
            ];
            $run->save();
        } catch (Throwable $saveError) {
            // Si la excepción original fue una caída de conexión, este save también falla.
            // El hook `failed()` de la cola es la red que vuelve a intentarlo.
            report($saveError);
        }
    }

    /**
     * Prorratea los conceptos de cobro activos del condominio entre sus unidades,
     * generando una `invoice` (+ sus `invoice_items`) por unidad facturable.
     *
     * @return array{unidades_facturadas: int, unidades_omitidas: int, detalle_omitidas: list<array{property_id: string, motivo: string}>, conceptos_omitidos: list<array{property_id: string, charge_concept_id: string, motivo: string}>}
     */
    private function prorratear(EloquentBillingRun $run, EloquentBillingPeriod $period): array
    {
        $condominiumId = (string) $period->condominium_id;

        // R-COB-05 — "unidades activas" = no eliminadas (soft-delete). Decisión de negocio
        // confirmada: bajo Ley 675 el propietario paga la cuota de administración según su
        // coeficiente aunque la unidad esté vacía, en remodelación o "fuera de servicio",
        // así que el `property_status` NO exime de facturación. La única exclusión es el
        // borrado. Lectura cross-context read-only de Properties (ADR-002), scopeada al
        // mismo condominium_id que rige el periodo.
        $properties = EloquentProperty::query()
            ->where('condominium_id', $condominiumId)
            ->orderBy('id')
            ->get();

        $concepts = EloquentChargeConcept::query()
            ->where('condominium_id', $condominiumId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        // Coeficientes de TODAS las unidades en una sola query (antes: una por unidad).
        // Con 300 unidades eso eran 300 round-trips que, sumados a los inserts fila a
        // fila, llevaban la corrida a rozar el timeout del worker — y un job que muere por
        // timeout dejaba el periodo bloqueado. El N+1 no era latencia: era la mecha.
        $coefficients = EloquentPropertyCoefficient::query()
            ->whereIn('property_id', $properties->pluck('id')->all())
            ->where('tipo', 'copropiedad')
            ->whereNull('vigente_hasta')
            ->get()
            ->keyBy('property_id');

        $facturadas = 0;
        $omitidas = [];
        $conceptosOmitidos = [];

        // Correlativo por periodo (no global del condominio): junto con
        // `UNIQUE(condominium_id, numero)` da una segunda red contra la duplicación —
        // un segundo prorrateo del mismo periodo recalcularía los mismos números y
        // colisionaría. Antes, al contar todas las facturas del condominio, el segundo
        // run numeraba a continuación y pasaba inadvertido.
        $correlativo = EloquentInvoice::withTrashed()
            ->where('billing_period_id', $period->id)
            ->count() + 1;

        $ahora = now();
        $fechaEmision = $ahora->toDateString();
        $fechaVencimiento = $ahora->copy()->addDays(15)->toDateString();

        $invoiceRows = [];
        $itemRows = [];

        foreach ($properties as $property) {
            $propertyId = (string) $property->id;

            // R-COB-04 (Ley 675): el prorrateo exige un coeficiente de copropiedad
            // vigente. Sin él la unidad no se puede facturar — se omite con motivo.
            $coefficient = $coefficients->get($propertyId);

            if ($coefficient === null) {
                $omitidas[] = [
                    'property_id' => $propertyId,
                    'motivo' => 'sin coeficiente vigente',
                ];

                continue;
            }

            $baseCalculo = Decimal::toFloat($coefficient->valor, 'coeficiente de copropiedad');

            $items = [];
            $valorTotal = 0.0;

            foreach ($concepts as $concept) {
                $valor = $this->calcularValor($concept, $baseCalculo, $property);

                if ($valor === null) {
                    // Un concepto que no aplica a esta unidad se registra explícitamente.
                    // Sin esto, una unidad con `por_area` pero sin `area_m2` salía con una
                    // factura bien formada de total menor, indistinguible por query de una
                    // unidad a la que el concepto legítimamente no aplica: sub-facturación
                    // silenciosa y no auditable (hallazgo del council).
                    if ($concept->metodo_calculo !== 'manual') {
                        $conceptosOmitidos[] = [
                            'property_id' => $propertyId,
                            'charge_concept_id' => (string) $concept->id,
                            'motivo' => $this->motivoConceptoOmitido($concept, $property),
                        ];
                    }

                    continue;
                }

                $items[] = [
                    'charge_concept_id' => (string) $concept->id,
                    'descripcion' => $concept->nombre,
                    'valor' => $valor,
                    // R-COB-06: snapshot inmutable del coeficiente usado — solo tiene
                    // sentido para conceptos prorrateados por coeficiente.
                    'base_calculo' => $concept->metodo_calculo === 'coeficiente' ? $baseCalculo : null,
                ];

                $valorTotal += $valor;
            }

            if ($items === []) {
                $omitidas[] = [
                    'property_id' => $propertyId,
                    'motivo' => 'sin conceptos de cobro aplicables',
                ];

                continue;
            }

            $valorTotal = round($valorTotal, 2);
            $invoiceId = Uuid::uuid7()->toString();

            $invoiceRows[] = [
                'id' => $invoiceId,
                'condominium_id' => $condominiumId,
                'property_id' => $propertyId,
                'billing_period_id' => $period->id,
                'billing_run_id' => $run->id,
                'numero' => $this->formatearNumero(
                    Decimal::toInt($period->anio, 'año del periodo'),
                    Decimal::toInt($period->mes, 'mes del periodo'),
                    $correlativo,
                ),
                'fecha_emision' => $fechaEmision,
                'fecha_vencimiento' => $fechaVencimiento,
                'valor_total' => $valorTotal,
                // Factura recién emitida: saldo = total (aún sin pagos aplicados).
                // `estado` NO se almacena — es derivado en lectura (R-COB-08, COBRANZA-B04).
                'saldo' => $valorTotal,
                'created_by' => $run->ejecutado_por,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];

            foreach ($items as $item) {
                $itemRows[] = [
                    'id' => Uuid::uuid7()->toString(),
                    'invoice_id' => $invoiceId,
                    'charge_concept_id' => $item['charge_concept_id'],
                    'descripcion' => $item['descripcion'],
                    'valor' => $item['valor'],
                    'base_calculo' => $item['base_calculo'],
                    'created_by' => $run->ejecutado_por,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }

            $correlativo++;
            $facturadas++;
        }

        // Bulk insert en chunks (antes: un INSERT por factura y otro por cada ítem).
        // `insert()` no dispara eventos de modelo ni llena timestamps — por eso el id y las
        // fechas se arman a mano arriba.
        foreach (array_chunk($invoiceRows, 500) as $chunk) {
            EloquentInvoice::insert($chunk);
        }

        foreach (array_chunk($itemRows, 500) as $chunk) {
            DB::table('invoice_items')->insert($chunk);
        }

        // R-COB-10: la corrida deja el periodo en `facturado`.
        if ($period->estado === 'abierto') {
            $period->estado = 'facturado';
            $period->updated_by = $run->ejecutado_por;
            $period->save();
        }

        return [
            'unidades_facturadas' => $facturadas,
            'unidades_omitidas' => count($omitidas),
            'detalle_omitidas' => $omitidas,
            'conceptos_omitidos' => $conceptosOmitidos,
        ];
    }

    /**
     * Valor de un concepto para una unidad, según su `metodo_calculo`.
     *
     * Devuelve null cuando el concepto no aplica automáticamente a esta unidad —
     * `manual` (R-COB-07: los conceptos manuales se agregan con
     * `POST /invoices/{id}/items`, no por la corrida) y `por_area` sin `area_m2`.
     */
    private function calcularValor(EloquentChargeConcept $concept, float $baseCalculo, EloquentProperty $property): ?float
    {
        $valorBase = Decimal::toFloat($concept->valor_base, 'valor base del concepto');

        return match ($concept->metodo_calculo) {
            'coeficiente' => round($valorBase * $baseCalculo, 2),
            'fijo' => round($valorBase, 2),
            'por_area' => $property->area_m2 !== null
                ? round($valorBase * Decimal::toFloat($property->area_m2, 'área de la unidad'), 2)
                : null,
            'manual' => null,
            default => null,
        };
    }

    private function motivoConceptoOmitido(EloquentChargeConcept $concept, EloquentProperty $property): string
    {
        if ($concept->metodo_calculo === 'por_area' && $property->area_m2 === null) {
            return 'la unidad no tiene área registrada';
        }

        return 'el concepto no aplica a esta unidad';
    }

    private function formatearNumero(int $anio, int $mes, int $correlativo): string
    {
        return sprintf('FAC-%04d%02d-%05d', $anio, $mes, $correlativo);
    }
}
