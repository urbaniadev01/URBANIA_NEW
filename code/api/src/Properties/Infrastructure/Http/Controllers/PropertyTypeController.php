<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Urbania\Properties\Infrastructure\Http\Requests\PropertyType\StorePropertyTypeRequest;
use Urbania\Properties\Infrastructure\Http\Requests\PropertyType\UpdatePropertyTypeRequest;
use Urbania\Properties\Infrastructure\Http\Resources\PropertyTypeResource;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyType;

final readonly class PropertyTypeController
{
    /**
     * List all property types available to the user's organization.
     * Includes system types (organization_id IS NULL) + tenant's own types.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        $query = EloquentPropertyType::query()
            ->where(function ($q) use ($organizationId): void {
                $q->whereNull('organization_id');

                if ($organizationId !== null) {
                    $q->orWhere('organization_id', $organizationId);
                }
            });

        $types = $query->orderBy('nombre')->get();

        return response()->json([
            'data' => PropertyTypeResource::collection($types),
        ]);
    }

    /**
     * Store a new property type for the user's organization.
     */
    public function store(StorePropertyTypeRequest $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        // Check for duplicate name within the same organization
        $exists = EloquentPropertyType::query()
            ->where('organization_id', $organizationId)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower((string) $request->validated('nombre'))])
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => [
                    'code' => 'PROPERTY_TYPE_NAME_DUPLICATE',
                    'message' => 'Ya existe un tipo de propiedad con ese nombre en esta organización.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        $type = new EloquentPropertyType([
            'organization_id' => $organizationId,
            'nombre' => (string) $request->validated('nombre'),
            'descripcion' => $request->validated('descripcion') ?? null,
            'created_by' => $user?->id,
        ]);
        $type->save();

        return (new PropertyTypeResource($type))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single property type (scoped by tenant).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $type = $this->findForTenant($request, $id);

        if ($type === null) {
            return $this->notFound('PROPERTY_TYPE_NOT_FOUND', 'Tipo de propiedad no encontrado.');
        }

        return response()->json([
            'data' => new PropertyTypeResource($type),
        ]);
    }

    /**
     * Update a property type (own org only — system types are immutable).
     */
    public function update(UpdatePropertyTypeRequest $request, string $id): JsonResponse
    {
        $type = $this->findForTenant($request, $id);

        if ($type === null) {
            return $this->notFound('PROPERTY_TYPE_NOT_FOUND', 'Tipo de propiedad no encontrado.');
        }

        // R-08: System catalogs are immutable
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

        // Check for duplicate name
        if ($request->has('nombre')) {
            $nombre = (string) $request->validated('nombre');
            $exists = EloquentPropertyType::query()
                ->where('organization_id', $type->organization_id)
                ->where('id', '!=', $id)
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                ->exists();

            if ($exists) {
                return response()->json([
                    'error' => [
                        'code' => 'PROPERTY_TYPE_NAME_DUPLICATE',
                        'message' => 'Ya existe un tipo de propiedad con ese nombre en esta organización.',
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

        return (new PropertyTypeResource($type->fresh()))->response();
    }

    /**
     * Delete a property type (own org only, not in use).
     */
    public function destroy(Request $request, string $id): Response|JsonResponse
    {
        $type = $this->findForTenant($request, $id);

        if ($type === null) {
            return $this->notFound('PROPERTY_TYPE_NOT_FOUND', 'Tipo de propiedad no encontrado.');
        }

        // R-08: System catalogs are immutable
        if ($type->organization_id === null) {
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
            ->where('property_type_id', $id)
            ->whereNull('deleted_at')
            ->exists();

        if ($inUse) {
            return response()->json([
                'error' => [
                    'code' => 'PROPERTY_TYPE_IN_USE',
                    'message' => 'No se puede eliminar el tipo porque está siendo utilizado por propiedades activas.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        $type->delete();

        return response()->noContent();
    }

    /**
     * Find a property type scoped by the user's tenant.
     * Includes system types + user's own org types, excluding other orgs.
     */
    private function findForTenant(Request $request, string $id): ?EloquentPropertyType
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        return EloquentPropertyType::query()
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
