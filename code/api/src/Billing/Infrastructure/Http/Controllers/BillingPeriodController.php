<?php

declare(strict_types=1);

namespace Urbania\Billing\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Urbania\Billing\Infrastructure\Http\Concerns\HasBillingPermission;
use Urbania\Billing\Infrastructure\Http\Requests\BillingPeriod\StoreBillingPeriodRequest;
use Urbania\Billing\Infrastructure\Http\Requests\BillingPeriod\UpdateBillingPeriodRequest;
use Urbania\Billing\Infrastructure\Http\Resources\BillingPeriodResource;
use Urbania\Billing\Infrastructure\Models\EloquentBillingPeriod;
use Urbania\Billing\Infrastructure\Models\EloquentInvoice;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;

/**
 * Periodos de facturación (COBRANZA-B03).
 *
 * R-COB-01/R-COB-02: tenant isolation + scope de staff (organization/condominium).
 * R-COB-10: ciclo de vida `abierto → facturado → cerrado`.
 * R-COB-08-bis: cerrar un periodo con facturas pendientes/parciales NO bloquea —
 * responde 200 con `warnings[]` (`BILLING_PERIOD_HAS_PENDING_INVOICES`), reutilizando
 * el mecanismo de warnings fijado en COBRANZA-B02 (ver api/API_CONTRACT.md §4-bis).
 */
final class BillingPeriodController
{
    use HasBillingPermission;

    /**
     * GET /condominiums/{condominium}/billing-periods
     */
    public function index(Request $request, string $condominium): JsonResponse
    {
        $parent = $this->findCondominiumForTenant($request, $condominium);

        if ($parent === null) {
            return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
        }

        if (! $this->hasBillingPermission($request, 'cobranza.periodos.ver', $condominium)) {
            return $this->forbidden('No tiene permisos para ver los periodos de facturación de este condominio.');
        }

        $periods = EloquentBillingPeriod::query()
            ->where('condominium_id', $condominium)
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->get();

        return response()->json([
            'data' => BillingPeriodResource::collection($periods),
        ]);
    }

    /**
     * POST /condominiums/{condominium}/billing-periods — abrir periodo.
     */
    public function store(StoreBillingPeriodRequest $request, string $condominium): JsonResponse
    {
        $parent = $this->findCondominiumForTenant($request, $condominium);

        if ($parent === null) {
            return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
        }

        if (! $this->hasBillingPermission($request, 'cobranza.facturacion.ejecutar', $condominium)) {
            return $this->forbidden('No tiene permisos para abrir periodos de facturación en este condominio.');
        }

        $anio = $request->integer('anio');
        $mes = $request->integer('mes');

        $exists = EloquentBillingPeriod::query()
            ->where('condominium_id', $condominium)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->exists();

        if ($exists) {
            // 409 (no 422): conflicto de unicidad de un recurso ya existente, mismo
            // criterio que CHARGE_CONCEPT_NAME_DUPLICATE / PROPERTY_CODE_DUPLICATE.
            // El criterio de aceptación #2 de la tarjeta decía 422 — desviación
            // confirmada por el usuario como estándar del API (ver LOCK-COBRANZA-03).
            return response()->json([
                'error' => [
                    'code' => 'BILLING_PERIOD_DUPLICATE',
                    'message' => 'Ya existe un periodo de facturación para ese año y mes en este condominio.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        $period = new EloquentBillingPeriod([
            'condominium_id' => $condominium,
            'anio' => $anio,
            'mes' => $mes,
            'estado' => 'abierto',
            'created_by' => $request->user()?->id,
        ]);
        $period->save();

        return (new BillingPeriodResource($period->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /billing-periods/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $period = $this->findPeriodForScope($request, $id, 'cobranza.periodos.ver');

        if ($period === null) {
            return $this->notFound('BILLING_PERIOD_NOT_FOUND', 'Periodo de facturación no encontrado.');
        }

        return (new BillingPeriodResource($period))->response();
    }

    /**
     * PATCH /billing-periods/{id} — cerrar periodo (R-COB-10, R-COB-08-bis).
     */
    public function update(UpdateBillingPeriodRequest $request, string $id): JsonResponse
    {
        $period = $this->findPeriodForScope($request, $id, 'cobranza.facturacion.ejecutar');

        if ($period === null) {
            return $this->notFound('BILLING_PERIOD_NOT_FOUND', 'Periodo de facturación no encontrado.');
        }

        if ($period->estado === 'cerrado') {
            return response()->json([
                'error' => [
                    'code' => 'BILLING_PERIOD_ALREADY_CLOSED',
                    'message' => 'El periodo de facturación ya está cerrado.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        // R-COB-08-bis: facturas con saldo pendiente NO bloquean el cierre — solo
        // producen un warning. `estado` de invoice es derivado (R-COB-08), así que se
        // consulta el hecho subyacente: saldo > 0.
        $pendingCount = EloquentInvoice::query()
            ->where('billing_period_id', $period->id)
            ->where('saldo', '>', 0)
            ->count();

        $period->estado = 'cerrado';
        $period->updated_by = $request->user()?->id;
        $period->save();

        $body = [
            'data' => new BillingPeriodResource($period->fresh()),
        ];

        if ($pendingCount > 0) {
            $body['warnings'] = [
                [
                    'code' => 'BILLING_PERIOD_HAS_PENDING_INVOICES',
                    'detail' => [
                        'invoices_pendientes' => $pendingCount,
                        'message' => 'El periodo se cerró con facturas pendientes o parciales — quedan abiertas para su cobro.',
                    ],
                ],
            ];
        }

        return response()->json($body, 200);
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function findCondominiumForTenant(Request $request, string $condominium): ?EloquentCondominium
    {
        return EloquentCondominium::query()
            ->where('id', $condominium)
            ->where('organization_id', $request->user()?->organization_id)
            ->first();
    }

    /**
     * Busca un periodo dentro del tenant + scope del actor. Devuelve null (→ 404)
     * si no existe, es de otra organización, o el actor no tiene el permiso
     * requerido sobre su condominio (anti-enumeración).
     */
    private function findPeriodForScope(Request $request, string $id, string $permission): ?EloquentBillingPeriod
    {
        $organizationId = $request->user()?->organization_id;

        $period = EloquentBillingPeriod::query()
            ->where('id', $id)
            ->whereHas('condominium', function ($q) use ($organizationId): void {
                $q->where('organization_id', $organizationId);
            })
            ->first();

        if ($period === null) {
            return null;
        }

        if (! $this->hasBillingPermission($request, $permission, $period->condominium_id)) {
            return null;
        }

        return $period;
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
