<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Urbania\Authorization\Infrastructure\Models\EloquentRoleAssignment;
use Urbania\Properties\Infrastructure\Http\Requests\Property\StorePropertyRequest;
use Urbania\Properties\Infrastructure\Http\Requests\Property\UpdatePropertyRequest;
use Urbania\Properties\Infrastructure\Http\Resources\PropertyListResource;
use Urbania\Properties\Infrastructure\Http\Resources\PropertyResource;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;
use Urbania\Properties\Infrastructure\Models\EloquentTower;

final readonly class PropertyController
{
    /**
     * List properties for a condominium with cursor-based pagination and filters.
     *
     * GET /condominiums/{id}/properties
     *
     * Filters: tower_id, type_id, status_id, search
     * Pagination: cursor-based (?cursor=...&limit=...)
     * Response: { data: [...], meta: { next_cursor: "..." } }
     *
     * R-09: Tenant isolation via condominium.
     * R-09-bis: Staff scoping (condominium/tower).
     * R-10: area_m2 NOT exposed in list (PropertyListResource).
     * CA 15: Residents (unit-only scope) get 403.
     */
    public function index(Request $request, string $condominium): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        // Verify the parent condominium exists and belongs to the user's org
        $parent = EloquentCondominium::query()
            ->where('id', $condominium)
            ->where('organization_id', $organizationId)
            ->first();

        if ($parent === null) {
            return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
        }

        // R-09-bis: Check staff scoping
        $condoScope = $this->getCondominiumScope($request);
        $towerScope = $this->getTowerScope($request);

        $hasCondoAccess = $condoScope['all'] || in_array($condominium, $condoScope['ids'], true);

        // If user has only unit scope (resident) and no org/condo/tower scope → 403
        if (! $hasCondoAccess && ! $towerScope['all']) {
            // Check if user has ANY org/condo scope at all
            $hasAnyPropertyScope = $this->hasPropertyAccessScope($request);
            if (! $hasAnyPropertyScope) {
                return $this->forbidden('No tiene permisos para listar unidades.');
            }
        }

        // Build query
        $query = EloquentProperty::query()
            ->where('condominium_id', $condominium);

        // Apply condominium scope filter
        if (! $condoScope['all']) {
            // If user has condo scope but not for this condominium, deny
            if ($condoScope['ids'] !== [] && ! $hasCondoAccess) {
                return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
            }

            // If user has only tower scope, filter to towers they can access
            if (! $towerScope['all'] && $towerScope['ids'] !== []) {
                // Get towers in this condominium that are in the user's tower scope
                $accessibleTowers = EloquentTower::query()
                    ->where('condominium_id', $condominium)
                    ->whereIn('id', $towerScope['ids'])
                    ->pluck('id')
                    ->toArray();

                if ($accessibleTowers === []) {
                    // No tower in this condominium matches user's tower scope → 404
                    return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
                }

                $query->whereIn('tower_id', $accessibleTowers);
            }
        }

        // Apply filters
        if ($request->filled('tower_id')) {
            $query->where('tower_id', $request->string('tower_id')->toString());
        }

        if ($request->filled('type_id')) {
            $query->where('property_type_id', $request->string('type_id')->toString());
        }

        if ($request->filled('status_id')) {
            $query->where('property_status_id', $request->string('status_id')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where('codigo', 'ilike', "%{$search}%");
        }

        // Cursor-based pagination using UUID v7 (time-ordered)
        $limit = min($request->integer('limit', 15), 50);
        $cursor = $request->string('cursor')->toString();

        if ($cursor !== '') {
            $query->where('id', '>', $cursor);
        }

        $query->orderBy('id');

        $properties = $query->limit($limit + 1)->get();

        // Determine next_cursor
        $hasMore = $properties->count() > $limit;
        $results = $hasMore ? $properties->slice(0, $limit) : $properties;
        $nextCursor = $hasMore ? $results->last()?->id : null;

        return response()->json([
            'data' => PropertyListResource::collection($results),
            'meta' => [
                'next_cursor' => $nextCursor,
            ],
        ]);
    }

    /**
     * Create a new property under a condominium.
     *
     * POST /condominiums/{id}/properties
     *
     * R-02: codigo unique per condominium_id → 409 PROPERTY_CODE_DUPLICATE.
     * R-07: condominium_id set from route, immutable.
     * R-11: created_by set to authenticated user.
     * CA 7: tower_id must belong to the same condominium → 422 TOWER_CONDOMINIUM_MISMATCH.
     */
    public function store(StorePropertyRequest $request, string $condominium): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        // Verify the parent condominium exists and belongs to the user's org
        $parent = EloquentCondominium::query()
            ->where('id', $condominium)
            ->where('organization_id', $organizationId)
            ->first();

        if ($parent === null) {
            return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
        }

        // Check access: user must have org or condominium scope for this condominium
        $condoScope = $this->getCondominiumScope($request);
        if (! $condoScope['all'] && ! in_array($condominium, $condoScope['ids'], true)) {
            return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
        }

        // R-02: Check for duplicate code in same condominium
        $codigo = (string) $request->validated('codigo');
        $exists = EloquentProperty::query()
            ->where('condominium_id', $condominium)
            ->where('codigo', $codigo)
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => [
                    'code' => 'PROPERTY_CODE_DUPLICATE',
                    'message' => 'Ya existe una unidad con ese código en este condominio.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        // CA 7: If tower_id is provided, it must belong to the same condominium
        $towerId = $request->validated('tower_id');
        if ($towerId !== null) {
            $tower = EloquentTower::query()
                ->where('id', $towerId)
                ->where('condominium_id', $condominium)
                ->first();

            if ($tower === null) {
                return response()->json([
                    'error' => [
                        'code' => 'TOWER_CONDOMINIUM_MISMATCH',
                        'message' => 'La torre no pertenece a este condominio.',
                        'trace_id' => (string) Str::orderedUuid(),
                    ],
                ], 422);
            }
        }

        $property = new EloquentProperty([
            'condominium_id' => $condominium,
            'tower_id' => $towerId,
            'property_type_id' => $request->validated('property_type_id'),
            'property_status_id' => $request->validated('property_status_id'),
            'codigo' => $codigo,
            'piso' => $request->validated('piso') ?? null,
            'area_m2' => $request->validated('area_m2') ?? null,
            'created_by' => $user?->id,
        ]);
        $property->save();

        return (new PropertyResource($property))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single property with full detail (including area_m2).
     *
     * GET /properties/{id}
     *
     * R-10: area_m2 IS exposed in detail (PropertyResource).
     * R-09: Tenant isolation via condominium.
     * R-09-bis: Staff scoping.
     * CA 16: Residents can see their own unit.
     * CA 17: Residents get 404 for other units (anti-enumeration).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $property = $this->findForTenantWithScope($request, $id);

        if ($property === null) {
            return $this->notFound('PROPERTY_NOT_FOUND', 'Unidad no encontrada.');
        }

        // Eager load relations for the detail view
        $property->load(['type', 'status', 'tower', 'condominium']);

        return (new PropertyResource($property))->response();
    }

    /**
     * Update a property.
     *
     * PATCH /properties/{id}
     *
     * R-02: codigo unique per condominium_id on change.
     * R-07: condominium_id is immutable — ignored if sent.
     * R-11: updated_by set to authenticated user.
     * CA 7: tower_id must belong to the same condominium (if changed).
     */
    public function update(UpdatePropertyRequest $request, string $id): JsonResponse
    {
        $property = $this->findForTenantWithScope($request, $id);

        if ($property === null) {
            return $this->notFound('PROPERTY_NOT_FOUND', 'Unidad no encontrada.');
        }

        $user = $request->user();

        // R-07: condominium_id is immutable — explicitly ignored

        // R-02: Check for duplicate code
        if ($request->has('codigo')) {
            $codigo = (string) $request->validated('codigo');
            $exists = EloquentProperty::query()
                ->where('condominium_id', $property->condominium_id)
                ->where('id', '!=', $id)
                ->where('codigo', $codigo)
                ->exists();

            if ($exists) {
                return response()->json([
                    'error' => [
                        'code' => 'PROPERTY_CODE_DUPLICATE',
                        'message' => 'Ya existe una unidad con ese código en este condominio.',
                        'trace_id' => (string) Str::orderedUuid(),
                    ],
                ], 409);
            }

            $property->codigo = $codigo;
        }

        // CA 7: If tower_id is being changed, it must belong to the same condominium
        if ($request->has('tower_id')) {
            $towerId = $request->validated('tower_id');

            if ($towerId !== null) {
                $tower = EloquentTower::query()
                    ->where('id', $towerId)
                    ->where('condominium_id', $property->condominium_id)
                    ->first();

                if ($tower === null) {
                    return response()->json([
                        'error' => [
                            'code' => 'TOWER_CONDOMINIUM_MISMATCH',
                            'message' => 'La torre no pertenece a este condominio.',
                            'trace_id' => (string) Str::orderedUuid(),
                        ],
                    ], 422);
                }
            }

            $property->tower_id = $towerId;
        }

        if ($request->has('property_type_id')) {
            $property->property_type_id = $request->validated('property_type_id');
        }

        if ($request->has('property_status_id')) {
            $property->property_status_id = $request->validated('property_status_id');
        }

        if ($request->has('piso')) {
            $property->piso = $request->validated('piso');
        }

        if ($request->has('area_m2')) {
            $property->area_m2 = $request->validated('area_m2');
        }

        $property->updated_by = $user?->id;
        $property->save();

        return (new PropertyResource($property->fresh()))->response();
    }

    /**
     * Delete a property (soft delete).
     *
     * DELETE /properties/{id}
     *
     * R-03: Cannot delete if it has active occupants → 409 PROPERTY_HAS_OCCUPANTS.
     *   If property_occupants table does not exist yet (DIRECTORIO pending),
     *   uses a guard clause that assumes "no occupants" with @todo.
     * R-04: Soft delete.
     */
    public function destroy(Request $request, string $id): Response|JsonResponse
    {
        $property = $this->findForTenantWithScope($request, $id);

        if ($property === null) {
            return $this->notFound('PROPERTY_NOT_FOUND', 'Unidad no encontrada.');
        }

        // R-03: Cannot delete if it has active occupants
        // @todo: When DIRECTORIO-B01 creates property_occupants table,
        // replace this guard clause with a real query against property_occupants.
        if (Schema::hasTable('property_occupants')) {
            $hasOccupants = DB::table('property_occupants')
                ->where('property_id', $id)
                ->exists();

            if ($hasOccupants) {
                return response()->json([
                    'error' => [
                        'code' => 'PROPERTY_HAS_OCCUPANTS',
                        'message' => 'No se puede eliminar la unidad porque tiene ocupantes activos. Retire los ocupantes primero.',
                        'trace_id' => (string) Str::orderedUuid(),
                    ],
                ], 409);
            }
        }

        $property->delete();

        return response()->noContent();
    }

    // ---------------------------------------------------------------
    // Private helpers — scoping and authorization
    // ---------------------------------------------------------------

    /**
     * Find a property by id, scoped to the user's tenant and staff/resident scope.
     *
     * R-09: Only properties belonging to condominiums in the user's organization.
     * R-09-bis: Only properties in the user's staff scope.
     * R-10: Returns null (mapped to 404) for properties outside scope — anti-enumeration.
     * CA 16/17: Residents can only see their own unit (via unit scope).
     */
    private function findForTenantWithScope(Request $request, string $id): ?EloquentProperty
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

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
        // User passes if they have:
        //   - org scope (all access)
        //   - condominium scope matching this property's condominium
        //   - tower scope matching this property's tower_id
        //   - unit scope matching this property's id (resident)

        $condoScope = $this->getCondominiumScope($request);
        $unitScope = $this->getUnitScope($request);

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

        // Unit scope (resident) → access if property ID matches
        if (in_array($id, $unitScope, true)) {
            return $property;
        }

        return null;
    }

    /**
     * Check if user has any scope that grants access to properties
     * (org, condominium, or tower — not just unit/resident).
     */
    private function hasPropertyAccessScope(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        return EloquentRoleAssignment::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->whereIn('scope_type', ['organization', 'condominium', 'tower'])
            ->exists();
    }

    /**
     * Get the user's effective scope for condominium access.
     *
     * @return array{all: bool, ids: string[]}
     */
    private function getCondominiumScope(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return ['all' => false, 'ids' => []];
        }

        $assignments = EloquentRoleAssignment::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->get();

        $hasOrgScope = $assignments->contains(
            fn (EloquentRoleAssignment $a): bool => $a->scope_type === 'organization',
        );

        if ($hasOrgScope) {
            return ['all' => true, 'ids' => []];
        }

        $ids = $assignments
            ->filter(fn (EloquentRoleAssignment $a): bool => $a->scope_type === 'condominium' && $a->scope_id !== null)
            ->pluck('scope_id')
            ->unique()
            ->values()
            ->toArray();

        return ['all' => false, 'ids' => $ids];
    }

    /**
     * Get the user's effective scope for tower access.
     *
     * Returns ['all' => true] if user has org or condominium scope.
     * Returns ['all' => false, 'ids' => [tower_ids...]] if user has tower-scoped assignments.
     *
     * @return array{all: bool, ids: string[]}
     */
    private function getTowerScope(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return ['all' => false, 'ids' => []];
        }

        $assignments = EloquentRoleAssignment::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->get();

        $hasBroadScope = $assignments->contains(
            fn (EloquentRoleAssignment $a): bool => in_array($a->scope_type, ['organization', 'condominium'], true),
        );

        if ($hasBroadScope) {
            return ['all' => true, 'ids' => []];
        }

        $ids = $assignments
            ->filter(fn (EloquentRoleAssignment $a): bool => $a->scope_type === 'tower' && $a->scope_id !== null)
            ->pluck('scope_id')
            ->unique()
            ->values()
            ->toArray();

        return ['all' => false, 'ids' => $ids];
    }

    /**
     * Get raw tower scope IDs (without broadening via org/condo).
     *
     * @return string[]
     */
    private function getRawTowerScopeIds(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return EloquentRoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('scope_type', 'tower')
            ->whereNotNull('scope_id')
            ->whereNull('deleted_at')
            ->pluck('scope_id')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get the user's unit scope — property IDs the user is assigned as a resident.
     *
     * @return string[]
     */
    private function getUnitScope(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return EloquentRoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('scope_type', 'unit')
            ->whereNotNull('scope_id')
            ->whereNull('deleted_at')
            ->pluck('scope_id')
            ->unique()
            ->values()
            ->toArray();
    }

    // ---------------------------------------------------------------
    // Standard response helpers
    // ---------------------------------------------------------------

    /**
     * Return a standard 403 forbidden response.
     */
    private function forbidden(string $message): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => $message,
                'trace_id' => (string) Str::orderedUuid(),
            ],
        ], 403);
    }

    /**
     * Return a standard 404 not found response.
     */
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
