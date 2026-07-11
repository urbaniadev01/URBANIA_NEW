<?php

declare(strict_types=1);

namespace Urbania\Billing\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Urbania\Billing\Application\Jobs\RunBillingPeriodJob;
use Urbania\Billing\Infrastructure\Http\Concerns\HasBillingPermission;
use Urbania\Billing\Infrastructure\Http\Resources\BillingRunResource;
use Urbania\Billing\Infrastructure\Models\EloquentBillingPeriod;
use Urbania\Billing\Infrastructure\Models\EloquentBillingRun;

/**
 * Corridas de facturación (COBRANZA-B03).
 *
 * R-COB-22: `POST` responde `202` de inmediato con el run en `en_proceso`; el
 * prorrateo real corre en `RunBillingPeriodJob` (cola). El cliente hace polling sobre
 * `GET /billing-runs/{id}` hasta `completado`/`fallido` — patrón "202 + polling",
 * documentado como convención general en `api/API_CONTRACT.md` §4-ter.
 *
 * R-COB-09: un solo `billing_run` `completado` por periodo — verificado en aplicación
 * (aquí) y reforzado por el UNIQUE parcial de BD creado en COBRANZA-B01.
 */
final class BillingRunController
{
    use HasBillingPermission;

    /**
     * POST /billing-periods/{billing_period}/billing-runs → 202
     */
    public function store(Request $request, string $billingPeriod): JsonResponse
    {
        $period = $this->findPeriodForTenant($request, $billingPeriod);

        if ($period === null) {
            return $this->notFound('BILLING_PERIOD_NOT_FOUND', 'Periodo de facturación no encontrado.');
        }

        // CA 9: segregación explícita — un usuario con `cobranza.periodos.ver` pero sin
        // `cobranza.facturacion.ejecutar` recibe 403 (no 404): ya demostró poder ver el
        // periodo, así que ocultarlo no aporta nada y el 403 es la señal correcta.
        if (! $this->hasBillingPermission($request, 'cobranza.facturacion.ejecutar', $period->condominium_id)) {
            return $this->forbidden('No tiene permisos para ejecutar la facturación de este condominio.');
        }

        if ($period->estado === 'cerrado') {
            return response()->json([
                'error' => [
                    'code' => 'BILLING_PERIOD_ALREADY_CLOSED',
                    'message' => 'No se puede facturar un periodo cerrado.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        // R-COB-09: no se permite una corrida nueva si ya hay una `en_proceso` o una
        // `completado` para este periodo.
        //
        // El check y la creación van dentro de una transacción con `lockForUpdate` sobre
        // la fila del periodo: antes eran un check-then-act sin lock, así que dos POST
        // simultáneos (doble clic, retry del cliente) pasaban ambos y encolaban dos
        // corridas. El UNIQUE parcial de BD solo cubre `completado`, no `en_proceso`, así
        // que no las frenaba. Serializar por el periodo cierra esa ventana acá, y evita
        // el trabajo desperdiciado de prorratear para después revertir.
        $blocking = null;

        $run = DB::transaction(function () use ($request, $period, &$blocking): ?EloquentBillingRun {
            EloquentBillingPeriod::query()->whereKey($period->id)->lockForUpdate()->first();

            $blocking = EloquentBillingRun::query()
                ->where('billing_period_id', $period->id)
                ->whereIn('estado', ['en_proceso', 'completado'])
                ->first();

            if ($blocking !== null) {
                return null;
            }

            return EloquentBillingRun::create([
                'billing_period_id' => $period->id,
                'ejecutado_por' => $request->user()?->id,
                'fecha' => now(),
                'estado' => 'en_proceso',
            ]);
        });

        if ($run === null) {
            return response()->json([
                'error' => [
                    'code' => 'BILLING_RUN_ALREADY_EXISTS',
                    'message' => $blocking?->estado === 'en_proceso'
                        ? 'Ya hay una corrida de facturación en proceso para este periodo.'
                        : 'Este periodo ya fue facturado por una corrida completada.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        RunBillingPeriodJob::dispatch((string) $run->id);

        return (new BillingRunResource($run->fresh()))
            ->response()
            ->setStatusCode(202);
    }

    /**
     * GET /billing-periods/{billing_period}/billing-runs
     */
    public function index(Request $request, string $billingPeriod): JsonResponse
    {
        $period = $this->findPeriodForTenant($request, $billingPeriod);

        if ($period === null) {
            return $this->notFound('BILLING_PERIOD_NOT_FOUND', 'Periodo de facturación no encontrado.');
        }

        if (! $this->hasBillingPermission($request, 'cobranza.periodos.ver', $period->condominium_id)) {
            return $this->forbidden('No tiene permisos para ver las corridas de facturación de este condominio.');
        }

        $runs = EloquentBillingRun::query()
            ->where('billing_period_id', $period->id)
            ->orderByDesc('fecha')
            ->get();

        return response()->json([
            'data' => BillingRunResource::collection($runs),
        ]);
    }

    /**
     * GET /billing-runs/{billing_run} — endpoint de polling (R-COB-22).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        $run = EloquentBillingRun::query()
            ->where('id', $id)
            ->whereHas('billingPeriod.condominium', function ($q) use ($organizationId): void {
                $q->where('organization_id', $organizationId);
            })
            ->first();

        if ($run === null) {
            return $this->notFound('BILLING_RUN_NOT_FOUND', 'Corrida de facturación no encontrada.');
        }

        $condominiumId = (string) $run->billingPeriod->condominium_id;

        if (! $this->hasBillingPermission($request, 'cobranza.periodos.ver', $condominiumId)) {
            return $this->notFound('BILLING_RUN_NOT_FOUND', 'Corrida de facturación no encontrada.');
        }

        return (new BillingRunResource($run))->response();
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function findPeriodForTenant(Request $request, string $id): ?EloquentBillingPeriod
    {
        $organizationId = $request->user()?->organization_id;

        return EloquentBillingPeriod::query()
            ->where('id', $id)
            ->whereHas('condominium', function ($q) use ($organizationId): void {
                $q->where('organization_id', $organizationId);
            })
            ->first();
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
