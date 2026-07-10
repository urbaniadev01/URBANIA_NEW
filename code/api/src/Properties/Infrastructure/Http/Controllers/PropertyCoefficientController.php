<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Urbania\Properties\Application\Services\CoefficientService;
use Urbania\Properties\Domain\Exceptions\PropertyNotInCondominiumException;
use Urbania\Properties\Infrastructure\Http\Concerns\HasScopeResolution;
use Urbania\Properties\Infrastructure\Http\Concerns\HasStandardResponses;
use Urbania\Properties\Infrastructure\Http\Requests\Coefficient\PatchCoefficientsRequest;
use Urbania\Properties\Infrastructure\Http\Resources\PropertyCoefficientResource;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;

final readonly class PropertyCoefficientController
{
    use HasScopeResolution;
    use HasStandardResponses;

    public function __construct(
        private CoefficientService $coefficientService,
    ) {}

    /**
     * List coefficients for a property (active + historical).
     *
     * GET /properties/{id}/coefficients
     *
     * R-09: Tenant isolation via property → condominium.
     * R-10: Only admin/staff/resident (own unit) can view coefficients.
     * CA 12: Residents can see their own property's coefficients.
     * CA 13: Residents get 404 for other properties (anti-enumeration).
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $property = $this->findPropertyForTenantWithScope($request, $id);

        if ($property === null) {
            return $this->notFound('PROPERTY_NOT_FOUND', 'Unidad no encontrada.');
        }

        $coefficients = $this->coefficientService->getCoefficientsForProperty($id);

        return response()->json([
            'data' => PropertyCoefficientResource::collection($coefficients),
        ]);
    }

    /**
     * Bulk update coefficients for a condominium (atomic transaction).
     *
     * PATCH /condominiums/{id}/coefficients
     *
     * Body: { items: [{property_id, tipo, valor}, ...] }
     *
     * R-05: Automatically closes previous active coefficient of same tipo.
     * R-06: Validates copropiedad sum = 1.0 → warning if not.
     * R-06-bis: tipo must be in closed set → 422 COEFFICIENT_INVALID_TYPE.
     * R-09-bis: Only users with org/condominium scope can manage coefficients.
     *   Tower scope is insufficient (PANORAMA §5 R-09-bis exception).
     * R-11: created_by/updated_by set to authenticated user.
     *
     * Atomic: all operations succeed or none (DB transaction).
     */
    public function patch(PatchCoefficientsRequest $request, string $condominium): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        // Verify condominium exists and belongs to user's org
        $condo = EloquentCondominium::query()
            ->where('id', $condominium)
            ->where('organization_id', $organizationId)
            ->first();

        if ($condo === null) {
            return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
        }

        // R-09-bis: Only org or condominium scope can manage coefficients
        // Tower scope is INSUFFICIENT for coefficients (financial data)
        $condoScope = $this->getCondominiumScope($request);
        if (! $condoScope['all'] && ! in_array($condominium, $condoScope['ids'], true)) {
            return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
        }

        $items = $request->validated('items');

        try {
            $result = $this->coefficientService->applyCoefficients(
                $condominium,
                $items,
                (string) $user?->id,
            );
        } catch (PropertyNotInCondominiumException $e) {
            return response()->json([
                'error' => [
                    'code' => 'PROPERTY_NOT_IN_CONDOMINIUM',
                    'message' => 'La unidad no pertenece a este condominio.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 422);
        }

        $response = response()->json([
            'data' => PropertyCoefficientResource::collection($result['data']),
        ]);

        // Include warnings if present (R-06: COEFFICIENT_SUM_MISMATCH)
        if ($result['warnings'] !== []) {
            $response->setData(array_merge(
                $response->getData(true),
                ['warnings' => $result['warnings']],
            ));
        }

        return $response;
    }

    /**
     * Find a property by id, scoped to the user's tenant and staff/resident scope.
     *
     * R-09: Only properties belonging to condominiums in the user's organization.
     * R-09-bis: Only properties in the user's staff scope or unit scope (resident).
     *   Unit scope bypasses tenant isolation — explicit assignment to a unit
     *   is authorization regardless of organization membership (CA #12).
     * R-10: Returns null (mapped to 404) for properties outside scope — anti-enumeration.
     * CA 12/13: Residents can only see their own unit (via unit scope).
     */
    private function findPropertyForTenantWithScope(Request $request, string $id): ?EloquentProperty
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        // Unit scope bypasses tenant isolation — explicit assignment to a unit
        // is authorization regardless of organization membership (R-09-bis, CA #12).
        $unitScope = $this->getUnitScope($request);
        if (in_array($id, $unitScope, true)) {
            return EloquentProperty::query()->where('id', $id)->first();
        }

        // Normal tenant isolation flow for admin/staff
        $property = EloquentProperty::query()
            ->where('id', $id)
            ->whereHas('condominium', function ($q) use ($organizationId): void {
                $q->where('organization_id', $organizationId);
            })
            ->first();

        if ($property === null) {
            return null;
        }

        // R-09-bis: Check scopes
        $condoScope = $this->getCondominiumScope($request);

        // Org scope → full access
        if ($condoScope['all']) {
            return $property;
        }

        // Condominium scope → access if condo matches
        if (in_array($property->condominium_id, $condoScope['ids'], true)) {
            return $property;
        }

        // Tower scope → access if property belongs to a scoped tower
        if ($property->tower_id !== null) {
            $towerScopeIds = $this->getRawTowerScopeIds($request);
            if (in_array($property->tower_id, $towerScopeIds, true)) {
                return $property;
            }
        }

        return null;
    }
}
