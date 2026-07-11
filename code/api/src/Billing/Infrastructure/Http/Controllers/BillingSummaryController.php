<?php

declare(strict_types=1);

namespace Urbania\Billing\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Urbania\Billing\Application\Support\Decimal;
use Urbania\Billing\Infrastructure\Http\Concerns\HasBillingPermission;
use Urbania\Billing\Infrastructure\Models\EloquentBillingPeriod;
use Urbania\Billing\Infrastructure\Models\EloquentInvoice;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;

/**
 * Panel de cartera de un periodo (COBRANZA-B03).
 *
 * `GET /condominiums/{id}/billing-periods/active/summary` es el endpoint que el widget
 * "Cuotas Pendientes" de DASHBOARD espera consumir — usa el permiso `billing.ver` (no
 * `cobranza.periodos.ver`) para no bloquear ese widget (CA 10). Ver la acción pendiente
 * cross-feature en `features/COBRANZA/BLOCKS.md`: el PANORAMA de DASHBOARD referencia
 * hoy `GET /billing-periods/active/summary` (sin condominio) — la ruta real es la de
 * acá, condominio-scoped, consistente con el resto del API.
 *
 * `estado` de una factura es derivado en lectura (R-COB-08), nunca almacenado: el
 * agregado se calcula sobre los hechos subyacentes (`saldo`, `valor_total`,
 * `fecha_vencimiento`).
 */
final class BillingSummaryController
{
    use HasBillingPermission;

    /**
     * GET /condominiums/{condominium}/billing-periods/active/summary
     *
     * "Activo" = el periodo más reciente que no esté `cerrado`.
     */
    public function active(Request $request, string $condominium): JsonResponse
    {
        $parent = EloquentCondominium::query()
            ->where('id', $condominium)
            ->where('organization_id', $request->user()?->organization_id)
            ->first();

        if ($parent === null) {
            return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
        }

        // CA 10: este endpoint usa `billing.ver` — el permiso "de entrada" del módulo,
        // el mismo que DASHBOARD ya usa para gatear su nav/widgets.
        if (! $this->hasBillingPermission($request, 'billing.ver', $condominium)) {
            return $this->forbidden('No tiene permisos para ver la cartera de este condominio.');
        }

        $period = EloquentBillingPeriod::query()
            ->where('condominium_id', $condominium)
            ->where('estado', '!=', 'cerrado')
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->first();

        if ($period === null) {
            return response()->json([
                'data' => [
                    'billing_period' => null,
                    'totales' => $this->totalesVacios(),
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'billing_period' => $this->periodoResumido($period),
                'totales' => $this->totales((string) $period->id),
            ],
        ]);
    }

    /**
     * GET /billing-periods/{billing_period}/summary
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        $period = EloquentBillingPeriod::query()
            ->where('id', $id)
            ->whereHas('condominium', function ($q) use ($organizationId): void {
                $q->where('organization_id', $organizationId);
            })
            ->first();

        if ($period === null) {
            return $this->notFound('BILLING_PERIOD_NOT_FOUND', 'Periodo de facturación no encontrado.');
        }

        if (! $this->hasBillingPermission($request, 'cobranza.periodos.ver', (string) $period->condominium_id)) {
            return $this->notFound('BILLING_PERIOD_NOT_FOUND', 'Periodo de facturación no encontrado.');
        }

        return response()->json([
            'data' => [
                'billing_period' => $this->periodoResumido($period),
                'totales' => $this->totales((string) $period->id),
            ],
        ]);
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function periodoResumido(EloquentBillingPeriod $period): array
    {
        return [
            'id' => $period->id,
            'condominium_id' => $period->condominium_id,
            'anio' => $period->anio,
            'mes' => $period->mes,
            'estado' => $period->estado,
        ];
    }

    /**
     * Agregados de cartera del periodo. `estado` de factura es derivado (R-COB-08):
     * `pagada` = saldo 0; `vencida` = saldo > 0 y ya venció; `parcial` = saldo > 0 pero
     * menor al total; `pendiente` = el resto.
     *
     * Se agrega en SQL, no hidratando modelos en PHP: este endpoint lo llama el widget de
     * cartera de DASHBOARD en cada refresh, y `invoices` es la tabla de mayor crecimiento
     * del sistema (unidades × meses). Con el índice sobre `billing_period_id` (agregado
     * junto con este cambio) el costo queda acotado al periodo, no a la tabla entera.
     *
     * @return array<string, mixed>
     */
    private function totales(string $billingPeriodId): array
    {
        $hoy = now()->startOfDay()->toDateString();

        $row = EloquentInvoice::query()
            ->where('billing_period_id', $billingPeriodId)
            ->selectRaw('COUNT(*) AS invoices_total')
            ->selectRaw('COALESCE(SUM(valor_total), 0) AS valor_facturado')
            ->selectRaw('COALESCE(SUM(saldo), 0) AS saldo_pendiente')
            ->selectRaw('COUNT(*) FILTER (WHERE saldo <= 0) AS invoices_pagadas')
            ->selectRaw('COUNT(*) FILTER (WHERE saldo > 0 AND fecha_vencimiento < ?) AS invoices_vencidas', [$hoy])
            ->selectRaw('COUNT(*) FILTER (WHERE saldo > 0 AND fecha_vencimiento >= ? AND saldo < valor_total) AS invoices_parciales', [$hoy])
            ->selectRaw('COUNT(*) FILTER (WHERE saldo > 0 AND fecha_vencimiento >= ? AND saldo >= valor_total) AS invoices_pendientes', [$hoy])
            ->first();

        if ($row === null) {
            return $this->totalesVacios();
        }

        $facturado = Decimal::toFloat($row->getAttribute('valor_facturado'), 'valor facturado');
        $pendiente = Decimal::toFloat($row->getAttribute('saldo_pendiente'), 'saldo pendiente');

        return [
            'invoices_total' => Decimal::toInt($row->getAttribute('invoices_total'), 'total de facturas'),
            'valor_facturado' => round($facturado, 2),
            'saldo_pendiente' => round($pendiente, 2),
            'valor_recaudado' => round($facturado - $pendiente, 2),
            'invoices_pagadas' => Decimal::toInt($row->getAttribute('invoices_pagadas'), 'facturas pagadas'),
            'invoices_vencidas' => Decimal::toInt($row->getAttribute('invoices_vencidas'), 'facturas vencidas'),
            'invoices_parciales' => Decimal::toInt($row->getAttribute('invoices_parciales'), 'facturas parciales'),
            'invoices_pendientes' => Decimal::toInt($row->getAttribute('invoices_pendientes'), 'facturas pendientes'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function totalesVacios(): array
    {
        return [
            'invoices_total' => 0,
            'valor_facturado' => 0.0,
            'saldo_pendiente' => 0.0,
            'valor_recaudado' => 0.0,
            'invoices_pagadas' => 0,
            'invoices_vencidas' => 0,
            'invoices_parciales' => 0,
            'invoices_pendientes' => 0,
        ];
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'PERMISSION_DENIED',
                'message' => $message,
                'trace_id' => (string) Str::orderedUuid(),
            ],
        ], 403);
    }

    private function notFound(string $code, string $message): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'trace_id' => (string) Str::orderedUuid(),
            ],
        ], 404);
    }
}
