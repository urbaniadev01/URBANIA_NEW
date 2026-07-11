<?php

declare(strict_types=1);

namespace Urbania\Directorio\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Urbania\Directorio\Infrastructure\Http\Requests\OccupantType\StoreOccupantTypeRequest;
use Urbania\Directorio\Infrastructure\Http\Requests\OccupantType\UpdateOccupantTypeRequest;
use Urbania\Directorio\Infrastructure\Http\Resources\OccupantTypeResource;
use Urbania\Directorio\Infrastructure\Models\EloquentOccupantType;
use Urbania\Directorio\Infrastructure\Models\EloquentPropertyOccupant;

final readonly class OccupantTypeController
{
    /**
     * List all occupant types available to the user's organization.
     * Includes system types (organization_id IS NULL) + tenant's own types.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        $query = EloquentOccupantType::query()
            ->where(function ($q) use ($organizationId): void {
                $q->whereNull('organization_id');

                if ($organizationId !== null) {
                    $q->orWhere('organization_id', $organizationId);
                }
            });

        $types = $query->orderBy('nombre')->get();

        return response()->json([
            'data' => OccupantTypeResource::collection($types),
        ]);
    }

    /**
     * Store a new occupant type for the user's organization.
     */
    public function store(StoreOccupantTypeRequest $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        $exists = EloquentOccupantType::query()
            ->where('organization_id', $organizationId)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower((string) $request->validated('nombre'))])
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => [
                    'code' => 'OCCUPANT_TYPE_NAME_DUPLICATE',
                    'message' => 'Ya existe un tipo de ocupante con ese nombre en esta organización.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        $type = new EloquentOccupantType([
            'organization_id' => $organizationId,
            'nombre' => (string) $request->validated('nombre'),
            'descripcion' => $request->validated('descripcion') ?? null,
            'created_by' => $user?->id,
        ]);
        $type->save();

        return (new OccupantTypeResource($type))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single occupant type (scoped by tenant).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $type = $this->findForTenant($request, $id);

        if ($type === null) {
            return $this->notFound('OCCUPANT_TYPE_NOT_FOUND', 'Tipo de ocupante no encontrado.');
        }

        return response()->json([
            'data' => new OccupantTypeResource($type),
        ]);
    }

    /**
     * Update an occupant type (own org only — system types are immutable).
     */
    public function update(UpdateOccupantTypeRequest $request, string $id): JsonResponse
    {
        $type = $this->findForTenant($request, $id);

        if ($type === null) {
            return $this->notFound('OCCUPANT_TYPE_NOT_FOUND', 'Tipo de ocupante no encontrado.');
        }

        // R-DIR-09: System catalogs are immutable
        if ($type->organization_id === null) {
            return response()->json([
                'error' => [
                    'code' => 'SYSTEM_CATALOG_READONLY',
                    'message' => 'Los catálogos del sistema no pueden ser modificados.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 403);
        }

        $user = $request->user();

        if ($request->has('nombre')) {
            $nombre = (string) $request->validated('nombre');
            $exists = EloquentOccupantType::query()
                ->where('organization_id', $type->organization_id)
                ->where('id', '!=', $id)
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                ->exists();

            if ($exists) {
                return response()->json([
                    'error' => [
                        'code' => 'OCCUPANT_TYPE_NAME_DUPLICATE',
                        'message' => 'Ya existe un tipo de ocupante con ese nombre en esta organización.',
                        'trace_id' => (string) Str::orderedUuid(),
                    ],
                ], 409);
            }

            $type->nombre = $nombre;
        }

        if ($request->has('descripcion')) {
            $type->descripcion = $request->validated('descripcion');
        }

        $type->updated_by = $user?->id;
        $type->save();

        return (new OccupantTypeResource($type->fresh()))->response();
    }

    /**
     * Delete an occupant type (own org only, not in use).
     */
    public function destroy(Request $request, string $id): Response|JsonResponse
    {
        $type = $this->findForTenant($request, $id);

        if ($type === null) {
            return $this->notFound('OCCUPANT_TYPE_NOT_FOUND', 'Tipo de ocupante no encontrado.');
        }

        // R-DIR-09: System catalogs are immutable
        if ($type->organization_id === null) {
            return response()->json([
                'error' => [
                    'code' => 'SYSTEM_CATALOG_READONLY',
                    'message' => 'Los catálogos del sistema no pueden ser eliminados.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 403);
        }

        $inUse = EloquentPropertyOccupant::query()
            ->where('occupant_type_id', $id)
            ->whereNull('deleted_at')
            ->exists();

        if ($inUse) {
            return response()->json([
                'error' => [
                    'code' => 'OCCUPANT_TYPE_IN_USE',
                    'message' => 'No se puede eliminar el tipo porque está siendo utilizado por ocupantes activos.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        $type->delete();

        return response()->noContent();
    }

    /**
     * Find an occupant type scoped by the user's tenant.
     * Includes system types + user's own org types, excluding other orgs.
     */
    private function findForTenant(Request $request, string $id): ?EloquentOccupantType
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        return EloquentOccupantType::query()
            ->where('id', $id)
            ->where(function ($q) use ($organizationId): void {
                $q->whereNull('organization_id');

                if ($organizationId !== null) {
                    $q->orWhere('organization_id', $organizationId);
                }
            })
            ->first();
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
