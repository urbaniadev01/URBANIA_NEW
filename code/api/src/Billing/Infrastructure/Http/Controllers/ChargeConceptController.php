<?php

declare(strict_types=1);

namespace Urbania\Billing\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Urbania\Billing\Infrastructure\Http\Concerns\HasBillingPermission;
use Urbania\Billing\Infrastructure\Http\Requests\ChargeConcept\StoreChargeConceptRequest;
use Urbania\Billing\Infrastructure\Http\Requests\ChargeConcept\UpdateChargeConceptRequest;
use Urbania\Billing\Infrastructure\Http\Resources\ChargeConceptResource;
use Urbania\Billing\Infrastructure\Models\EloquentChargeConcept;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;

/**
 * R-COB-01: tenant isolation vía condominium_id.
 * R-COB-02: aislamiento por scope de staff — datos financieros requieren scope
 * `condominium` u `organization` como mínimo (`tower`/`unit` NUNCA bastan aquí, a
 * diferencia de PropertyController que sí concede lectura con scope `tower`).
 * R-COB-18: warning no bloqueante en creación/edición de conceptos `fondo_imprevistos`.
 */
final class ChargeConceptController
{
    use HasBillingPermission;

    /**
     * GET /condominiums/{condominium}/charge-concepts
     */
    public function index(Request $request, string $condominium): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        $parent = EloquentCondominium::query()
            ->where('id', $condominium)
            ->where('organization_id', $organizationId)
            ->first();

        if ($parent === null) {
            return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
        }

        if (! $this->hasBillingPermission($request, 'cobranza.conceptos.ver', $condominium)) {
            return $this->forbidden('No tiene permisos para ver los conceptos de cobro de este condominio.');
        }

        $concepts = EloquentChargeConcept::query()
            ->where('condominium_id', $condominium)
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => ChargeConceptResource::collection($concepts),
        ]);
    }

    /**
     * POST /condominiums/{condominium}/charge-concepts
     */
    public function store(StoreChargeConceptRequest $request, string $condominium): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        $parent = EloquentCondominium::query()
            ->where('id', $condominium)
            ->where('organization_id', $organizationId)
            ->first();

        if ($parent === null) {
            return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
        }

        if (! $this->hasBillingPermission($request, 'cobranza.conceptos.gestionar', $condominium)) {
            return $this->forbidden('No tiene permisos para gestionar los conceptos de cobro de este condominio.');
        }

        $nombre = (string) $request->validated('nombre');

        $exists = EloquentChargeConcept::query()
            ->where('condominium_id', $condominium)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => [
                    'code' => 'CHARGE_CONCEPT_NAME_DUPLICATE',
                    'message' => 'Ya existe un concepto de cobro con ese nombre en este condominio.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        $tipo = (string) $request->validated('tipo');

        $concept = new EloquentChargeConcept([
            'condominium_id' => $condominium,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'metodo_calculo' => (string) $request->validated('metodo_calculo'),
            'valor_base' => $request->validated('valor_base'),
            'created_by' => $user?->id,
        ]);
        $concept->save();

        return $this->respondWithWarnings($concept->fresh(), $tipo, 201);
    }

    /**
     * GET /charge-concepts/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $concept = $this->findForCondominiumScope($request, $id);

        if ($concept === null) {
            return $this->notFound('CHARGE_CONCEPT_NOT_FOUND', 'Concepto de cobro no encontrado.');
        }

        return (new ChargeConceptResource($concept))->response();
    }

    /**
     * PATCH /charge-concepts/{id}
     */
    public function update(UpdateChargeConceptRequest $request, string $id): JsonResponse
    {
        $concept = $this->findForCondominiumScope($request, $id, 'cobranza.conceptos.gestionar');

        if ($concept === null) {
            return $this->notFound('CHARGE_CONCEPT_NOT_FOUND', 'Concepto de cobro no encontrado.');
        }

        if ($request->has('nombre')) {
            $nombre = (string) $request->validated('nombre');

            $exists = EloquentChargeConcept::query()
                ->where('condominium_id', $concept->condominium_id)
                ->where('id', '!=', $id)
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                ->exists();

            if ($exists) {
                return response()->json([
                    'error' => [
                        'code' => 'CHARGE_CONCEPT_NAME_DUPLICATE',
                        'message' => 'Ya existe un concepto de cobro con ese nombre en este condominio.',
                        'trace_id' => (string) Str::orderedUuid(),
                    ],
                ], 409);
            }

            $concept->nombre = $nombre;
        }

        if ($request->has('tipo')) {
            $concept->tipo = (string) $request->validated('tipo');
        }

        if ($request->has('metodo_calculo')) {
            $concept->metodo_calculo = (string) $request->validated('metodo_calculo');
        }

        if ($request->has('valor_base')) {
            $concept->valor_base = $request->validated('valor_base');
        }

        $concept->updated_by = $request->user()?->id;
        $concept->save();

        return $this->respondWithWarnings($concept->fresh(), $concept->tipo, 200);
    }

    /**
     * DELETE /charge-concepts/{id} — desactivación (activo=false + soft delete).
     */
    public function destroy(Request $request, string $id): Response|JsonResponse
    {
        $concept = $this->findForCondominiumScope($request, $id, 'cobranza.conceptos.gestionar');

        if ($concept === null) {
            return $this->notFound('CHARGE_CONCEPT_NOT_FOUND', 'Concepto de cobro no encontrado.');
        }

        $concept->activo = false;
        $concept->updated_by = $request->user()?->id;
        $concept->save();
        $concept->delete();

        return response()->noContent();
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    /**
     * Find a charge concept scoped to the user's tenant and staff scope
     * (R-COB-01, R-COB-02). Optionally checks a specific permission for the
     * concept's condominium — returns null (mapped to 404) if the permission
     * check fails, same as if the resource didn't exist.
     */
    private function findForCondominiumScope(Request $request, string $id, ?string $permission = null): ?EloquentChargeConcept
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        $concept = EloquentChargeConcept::query()
            ->where('id', $id)
            ->whereHas('condominium', function ($q) use ($organizationId): void {
                $q->where('organization_id', $organizationId);
            })
            ->first();

        if ($concept === null) {
            return null;
        }

        $requiredPermission = $permission ?? 'cobranza.conceptos.ver';

        if (! $this->hasBillingPermission($request, $requiredPermission, $concept->condominium_id)) {
            return null;
        }

        return $concept;
    }

    /**
     * R-COB-18: si tipo=fondo_imprevistos, agrega warnings[] no bloqueante al body
     * (ver api/API_CONTRACT.md §4-bis) — el warning nunca cambia el HTTP status.
     */
    private function respondWithWarnings(EloquentChargeConcept $concept, string $tipo, int $status): JsonResponse
    {
        $body = [
            'data' => new ChargeConceptResource($concept),
        ];

        if ($tipo === 'fondo_imprevistos') {
            $body['warnings'] = [
                [
                    'code' => 'FONDO_IMPREVISTOS_VALIDACION_PENDIENTE',
                    'detail' => [
                        'message' => 'La validación del mínimo legal (1%) para fondo de imprevistos no está implementada en esta fase.',
                    ],
                ],
            ];
        }

        return response()->json($body, $status);
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
