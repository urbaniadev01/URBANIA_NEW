<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Urbania\Authorization\Infrastructure\Models\EloquentRoleAssignment;
use Urbania\Properties\Infrastructure\Http\Requests\Condominium\StoreCondominiumRequest;
use Urbania\Properties\Infrastructure\Http\Requests\Condominium\UpdateCondominiumRequest;
use Urbania\Properties\Infrastructure\Http\Resources\CondominiumResource;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;

final readonly class CondominiumController
{
    /**
     * List all condominiums for the user's organization.
     *
     * R-09: Tenant isolation — only condominiums in the user's organization.
     * R-09-bis: Staff scoping — users with condominium scope only see their assigned condominiums.
     * CA 18: Users without condominium/org scope (e.g., pure residents) get 403.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        $scope = $this->getCondominiumScope($request);

        // If user has no access scope at all → 403 (CA 18)
        if (! $scope['all'] && $scope['ids'] === []) {
            return $this->forbidden('No tiene permisos para listar condominios.');
        }

        $query = EloquentCondominium::query()
            ->where('organization_id', $organizationId);

        // Apply scope filtering (R-09-bis)
        if (! $scope['all']) {
            $query->whereIn('id', $scope['ids']);
        }

        $condominiums = $query->orderBy('nombre')->get();

        return response()->json([
            'data' => CondominiumResource::collection($condominiums),
        ]);
    }

    /**
     * Create a new condominium for the user's organization.
     */
    public function store(StoreCondominiumRequest $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        // Check for duplicate name within the same organization
        $exists = EloquentCondominium::query()
            ->where('organization_id', $organizationId)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower((string) $request->validated('nombre'))])
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => [
                    'code' => 'CONDOMINIUM_NAME_DUPLICATE',
                    'message' => 'Ya existe un condominio con ese nombre en esta organización.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        $condominium = new EloquentCondominium([
            'organization_id' => $organizationId,
            'nombre' => (string) $request->validated('nombre'),
            'direccion' => $request->validated('direccion') ?? null,
            'nit' => $request->validated('nit') ?? null,
            'created_by' => $user?->id,
        ]);
        $condominium->save();

        return (new CondominiumResource($condominium))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single condominium, with towers included.
     *
     * R-09: Tenant isolation.
     * R-09-bis: Staff scoping — only accessible if in user's scope.
     * R-10: Anti-enumeration — 404 for condominiums from other orgs or outside scope.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $condominium = $this->findForTenantWithScope($request, $id);

        if ($condominium === null) {
            return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
        }

        $condominium->load('towers');

        return (new CondominiumResource($condominium))->response();
    }

    /**
     * Update a condominium.
     */
    public function update(UpdateCondominiumRequest $request, string $id): JsonResponse
    {
        $condominium = $this->findForTenantWithScope($request, $id);

        if ($condominium === null) {
            return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
        }

        $user = $request->user();

        // Check for duplicate name
        if ($request->has('nombre')) {
            $nombre = (string) $request->validated('nombre');
            $exists = EloquentCondominium::query()
                ->where('organization_id', $condominium->organization_id)
                ->where('id', '!=', $id)
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                ->exists();

            if ($exists) {
                return response()->json([
                    'error' => [
                        'code' => 'CONDOMINIUM_NAME_DUPLICATE',
                        'message' => 'Ya existe un condominio con ese nombre en esta organización.',
                        'trace_id' => (string) Str::orderedUuid(),
                    ],
                ], 409);
            }

            $condominium->nombre = $nombre;
        }

        if ($request->has('direccion')) {
            $condominium->direccion = $request->validated('direccion');
        }

        if ($request->has('nit')) {
            $condominium->nit = $request->validated('nit');
        }

        $condominium->updated_by = $user?->id;
        $condominium->save();

        return (new CondominiumResource($condominium->fresh()))->response();
    }

    /**
     * Delete a condominium.
     *
     * R-03: Cannot delete if it has active towers or properties.
     * R-04: Soft delete.
     */
    public function destroy(Request $request, string $id): Response|JsonResponse
    {
        $condominium = $this->findForTenantWithScope($request, $id);

        if ($condominium === null) {
            return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
        }

        // R-03: Check for active towers
        $hasTowers = $condominium->towers()
            ->whereNull('deleted_at')
            ->exists();

        if ($hasTowers) {
            return response()->json([
                'error' => [
                    'code' => 'CONDOMINIUM_HAS_TOWERS',
                    'message' => 'No se puede eliminar el condominio porque tiene torres activas. Elimine las torres primero.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        // R-03: Check for active properties
        $hasProperties = EloquentProperty::query()
            ->where('condominium_id', $id)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasProperties) {
            return response()->json([
                'error' => [
                    'code' => 'CONDOMINIUM_HAS_PROPERTIES',
                    'message' => 'No se puede eliminar el condominio porque tiene propiedades activas. Elimine las propiedades primero.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        $condominium->delete();

        return response()->noContent();
    }

    /**
     * Find a condominium by id, scoped to the user's tenant and staff scope.
     *
     * R-09: Only condominiums in the user's organization.
     * R-09-bis: Only condominiums in the user's staff scope (if applicable).
     * R-10: Returns null (mapped to 404) for condominiums outside scope — anti-enumeration.
     */
    private function findForTenantWithScope(Request $request, string $id): ?EloquentCondominium
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        $condominium = EloquentCondominium::query()
            ->where('id', $id)
            ->where('organization_id', $organizationId)
            ->first();

        if ($condominium === null) {
            return null;
        }

        // R-09-bis: Check staff scope
        $scope = $this->getCondominiumScope($request);
        if (! $scope['all'] && ! in_array($id, $scope['ids'], true)) {
            return null; // Outside user's scope → 404 (R-10 anti-enumeration)
        }

        return $condominium;
    }

    /**
     * Get the user's effective scope for condominium access.
     *
     * Returns:
     *   - ['all' => true, 'ids' => []] if user has organization scope (see everything)
     *   - ['all' => false, 'ids' => ['uuid', ...]] if user has condominium-scoped assignments
     *   - ['all' => false, 'ids' => []] if user has NO condominium/org scope (no access)
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

        // Organization scope → user can see all condominiums in their org
        $hasOrgScope = $assignments->contains(
            fn (EloquentRoleAssignment $a): bool => $a->scope_type === 'organization',
        );

        if ($hasOrgScope) {
            return ['all' => true, 'ids' => []];
        }

        // Condominium scope → user can only see specific condominiums
        $ids = $assignments
            ->filter(fn (EloquentRoleAssignment $a): bool => $a->scope_type === 'condominium' && $a->scope_id !== null)
            ->pluck('scope_id')
            ->unique()
            ->values()
            ->toArray();

        return ['all' => false, 'ids' => $ids];
    }

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
