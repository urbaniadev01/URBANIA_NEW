<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Urbania\Properties\Infrastructure\Http\Requests\PropertyStatus\StorePropertyStatusRequest;
use Urbania\Properties\Infrastructure\Http\Requests\PropertyStatus\UpdatePropertyStatusRequest;
use Urbania\Properties\Infrastructure\Http\Resources\PropertyStatusResource;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyStatus;

final readonly class PropertyStatusController
{
    /**
     * List all property statuses available to the user's organization.
     * Includes system statuses (organization_id IS NULL) + tenant's own statuses.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        $query = EloquentPropertyStatus::query()
            ->where(function ($q) use ($organizationId): void {
                $q->whereNull('organization_id');

                if ($organizationId !== null) {
                    $q->orWhere('organization_id', $organizationId);
                }
            });

        $statuses = $query->orderBy('nombre')->get();

        return response()->json([
            'data' => PropertyStatusResource::collection($statuses),
        ]);
    }

    /**
     * Store a new property status for the user's organization.
     */
    public function store(StorePropertyStatusRequest $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        // Check for duplicate name within the same organization
        $exists = EloquentPropertyStatus::query()
            ->where('organization_id', $organizationId)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower((string) $request->validated('nombre'))])
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => [
                    'code' => 'PROPERTY_STATUS_NAME_DUPLICATE',
                    'message' => 'Ya existe un estado de propiedad con ese nombre en esta organización.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        $status = new EloquentPropertyStatus([
            'organization_id' => $organizationId,
            'nombre' => (string) $request->validated('nombre'),
            'descripcion' => $request->validated('descripcion') ?? null,
            'created_by' => $user?->id,
        ]);
        $status->save();

        return (new PropertyStatusResource($status))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single property status (scoped by tenant).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $status = $this->findForTenant($request, $id);

        if ($status === null) {
            return $this->notFound('PROPERTY_STATUS_NOT_FOUND', 'Estado de propiedad no encontrado.');
        }

        return response()->json([
            'data' => new PropertyStatusResource($status),
        ]);
    }

    /**
     * Update a property status (own org only — system statuses are immutable).
     */
    public function update(UpdatePropertyStatusRequest $request, string $id): JsonResponse
    {
        $status = $this->findForTenant($request, $id);

        if ($status === null) {
            return $this->notFound('PROPERTY_STATUS_NOT_FOUND', 'Estado de propiedad no encontrado.');
        }

        // R-08: System catalogs are immutable
        if ($status->organization_id === null) {
            return response()->json([
                'error' => [
                    'code' => 'SYSTEM_CATALOG_READONLY',
                    'message' => 'Los catálogos del sistema no pueden ser modificados.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 403);
        }

        $user = $request->user();

        // Check for duplicate name
        if ($request->has('nombre')) {
            $nombre = (string) $request->validated('nombre');
            $exists = EloquentPropertyStatus::query()
                ->where('organization_id', $status->organization_id)
                ->where('id', '!=', $id)
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                ->exists();

            if ($exists) {
                return response()->json([
                    'error' => [
                        'code' => 'PROPERTY_STATUS_NAME_DUPLICATE',
                        'message' => 'Ya existe un estado de propiedad con ese nombre en esta organización.',
                        'trace_id' => (string) Str::orderedUuid(),
                    ],
                ], 409);
            }

            $status->nombre = $nombre;
        }

        if ($request->has('descripcion')) {
            $status->descripcion = $request->validated('descripcion');
        }

        $status->updated_by = $user?->id;
        $status->save();

        return response()->json([
            'data' => new PropertyStatusResource($status->fresh()),
        ]);
    }

    /**
     * Delete a property status (own org only, not in use).
     */
    public function destroy(Request $request, string $id): Response|JsonResponse
    {
        $status = $this->findForTenant($request, $id);

        if ($status === null) {
            return $this->notFound('PROPERTY_STATUS_NOT_FOUND', 'Estado de propiedad no encontrado.');
        }

        // R-08: System catalogs are immutable
        if ($status->organization_id === null) {
            return response()->json([
                'error' => [
                    'code' => 'SYSTEM_CATALOG_READONLY',
                    'message' => 'Los catálogos del sistema no pueden ser eliminados.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 403);
        }

        // R-03: Cannot delete if referenced by active properties
        $inUse = EloquentProperty::query()
            ->where('property_status_id', $id)
            ->whereNull('deleted_at')
            ->exists();

        if ($inUse) {
            return response()->json([
                'error' => [
                    'code' => 'PROPERTY_STATUS_IN_USE',
                    'message' => 'No se puede eliminar el estado porque está siendo utilizado por propiedades activas.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        $status->delete();

        return response()->noContent();
    }

    /**
     * Find a property status scoped by the user's tenant.
     * Includes system statuses + user's own org statuses, excluding other orgs.
     */
    private function findForTenant(Request $request, string $id): ?EloquentPropertyStatus
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        return EloquentPropertyStatus::query()
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
