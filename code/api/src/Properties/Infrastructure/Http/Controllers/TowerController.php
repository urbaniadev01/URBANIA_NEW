<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Urbania\Authorization\Infrastructure\Models\EloquentRoleAssignment;
use Urbania\Properties\Infrastructure\Http\Requests\Tower\StoreTowerRequest;
use Urbania\Properties\Infrastructure\Http\Requests\Tower\UpdateTowerRequest;
use Urbania\Properties\Infrastructure\Http\Resources\TowerResource;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;
use Urbania\Properties\Infrastructure\Models\EloquentTower;

final readonly class TowerController
{
    /**
     * List all towers for a given condominium.
     *
     * Nested route: GET /condominiums/{condominium}/towers
     *
     * R-09: Tenant isolation via the parent condominium.
     * R-09-bis: Staff scoping — only towers in the user's scope.
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

        // R-09-bis: Check if user has access to this condominium
        // User can access if they have: org scope, condominium scope for this condo,
        // OR tower scope for any tower in this condo
        $condoScope = $this->getCondominiumScope($request);
        $towerScope = $this->getTowerScope($request);

        $hasCondoAccess = $condoScope['all'] || in_array($condominium, $condoScope['ids'], true);
        $hasTowerInCondo = $towerScope['all'] || (! $hasCondoAccess && $this->hasTowerScopeInCondominium($request, $condominium));

        if (! $hasCondoAccess && ! $hasTowerInCondo) {
            return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
        }

        // Get towers for this condominium
        $query = EloquentTower::query()
            ->where('condominium_id', $condominium);

        // R-09-bis: Apply tower-level scope filter (only if user has tower-scoped restrictions)
        if (! $towerScope['all'] && $towerScope['ids'] !== []) {
            $query->whereIn('id', $towerScope['ids']);
        }

        $towers = $query->orderBy('nombre')->get();

        return response()->json([
            'data' => TowerResource::collection($towers),
        ]);
    }

    /**
     * Create a new tower under a condominium.
     *
     * Nested route: POST /condominiums/{condominium}/towers
     */
    public function store(StoreTowerRequest $request, string $condominium): JsonResponse
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

        // R-09-bis: Check if user has access to this condominium
        $condoScope = $this->getCondominiumScope($request);
        if (! $condoScope['all'] && ! in_array($condominium, $condoScope['ids'], true)) {
            // Users with only tower scope cannot create new towers
            return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
        }

        // Check for duplicate tower name within the same condominium
        $nombre = (string) $request->validated('nombre');
        $exists = EloquentTower::query()
            ->where('condominium_id', $condominium)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => [
                    'code' => 'TOWER_NAME_DUPLICATE',
                    'message' => 'Ya existe una torre con ese nombre en este condominio.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        $tower = new EloquentTower([
            'condominium_id' => $condominium,
            'nombre' => $nombre,
            'created_by' => $user?->id,
        ]);
        $tower->save();

        return (new TowerResource($tower))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single tower.
     *
     * R-09: Tenant isolation via the parent condominium.
     * R-09-bis: Staff scoping.
     * R-10: Anti-enumeration — 404 for towers outside user's scope.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tower = $this->findForTenantWithScope($request, $id);

        if ($tower === null) {
            return $this->notFound('TOWER_NOT_FOUND', 'Torre no encontrada.');
        }

        return (new TowerResource($tower))->response();
    }

    /**
     * Update a tower.
     *
     * R-07: condominium_id is immutable — the field is ignored if sent.
     */
    public function update(UpdateTowerRequest $request, string $id): JsonResponse
    {
        $tower = $this->findForTenantWithScope($request, $id);

        if ($tower === null) {
            return $this->notFound('TOWER_NOT_FOUND', 'Torre no encontrada.');
        }

        $user = $request->user();

        // Check for duplicate name
        if ($request->has('nombre')) {
            $nombre = (string) $request->validated('nombre');
            $exists = EloquentTower::query()
                ->where('condominium_id', $tower->condominium_id)
                ->where('id', '!=', $id)
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                ->exists();

            if ($exists) {
                return response()->json([
                    'error' => [
                        'code' => 'TOWER_NAME_DUPLICATE',
                        'message' => 'Ya existe una torre con ese nombre en este condominio.',
                        'trace_id' => (string) Str::orderedUuid(),
                    ],
                ], 409);
            }

            $tower->nombre = $nombre;
        }

        // R-07: condominium_id is immutable — explicitly ignored

        $tower->updated_by = $user?->id;
        $tower->save();

        return (new TowerResource($tower->fresh()))->response();
    }

    /**
     * Delete a tower.
     *
     * R-03: Cannot delete if it has active properties.
     * R-04: Soft delete.
     */
    public function destroy(Request $request, string $id): Response|JsonResponse
    {
        $tower = $this->findForTenantWithScope($request, $id);

        if ($tower === null) {
            return $this->notFound('TOWER_NOT_FOUND', 'Torre no encontrada.');
        }

        // R-03: Check for active properties
        $hasProperties = EloquentProperty::query()
            ->where('tower_id', $id)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasProperties) {
            return response()->json([
                'error' => [
                    'code' => 'TOWER_HAS_PROPERTIES',
                    'message' => 'No se puede eliminar la torre porque tiene propiedades activas. Elimine las propiedades primero.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        $tower->delete();

        return response()->noContent();
    }

    /**
     * Find a tower by id, scoped to the user's tenant and staff scope.
     *
     * R-09: Only towers belonging to condominiums in the user's organization.
     * R-09-bis: Only towers in the user's staff scope.
     * R-10: Returns null (mapped to 404) for towers outside scope — anti-enumeration.
     */
    private function findForTenantWithScope(Request $request, string $id): ?EloquentTower
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        $tower = EloquentTower::query()
            ->where('id', $id)
            ->whereHas('condominium', function ($q) use ($organizationId): void {
                $q->where('organization_id', $organizationId);
            })
            ->first();

        if ($tower === null) {
            return null;
        }

        // R-09-bis: Check scopes — user must pass EITHER condominium OR tower scope check
        $condoScope = $this->getCondominiumScope($request);
        $towerScope = $this->getTowerScope($request);

        $passesCondoScope = $condoScope['all'] || in_array($tower->condominium_id, $condoScope['ids'], true);
        $passesTowerScope = $towerScope['all'] || in_array($id, $towerScope['ids'], true);

        if (! $passesCondoScope && ! $passesTowerScope) {
            return null;
        }

        return $tower;
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

        // If user has org or condominium scope, they can see all towers in those scopes
        $hasBroadScope = $assignments->contains(
            fn (EloquentRoleAssignment $a): bool => in_array($a->scope_type, ['organization', 'condominium'], true),
        );

        if ($hasBroadScope) {
            return ['all' => true, 'ids' => []];
        }

        // Tower scope → limited to specific towers
        $ids = $assignments
            ->filter(fn (EloquentRoleAssignment $a): bool => $a->scope_type === 'tower' && $a->scope_id !== null)
            ->pluck('scope_id')
            ->unique()
            ->values()
            ->toArray();

        return ['all' => false, 'ids' => $ids];
    }

    /**
     * Check if the user has any tower-scoped assignment for a tower in the given condominium.
     */
    private function hasTowerScopeInCondominium(Request $request, string $condominiumId): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        $assignments = EloquentRoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('scope_type', 'tower')
            ->whereNotNull('scope_id')
            ->whereNull('deleted_at')
            ->pluck('scope_id')
            ->toArray();

        if ($assignments === []) {
            return false;
        }

        return EloquentTower::query()
            ->where('condominium_id', $condominiumId)
            ->whereIn('id', $assignments)
            ->exists();
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
