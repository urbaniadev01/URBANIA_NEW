<?php

declare(strict_types=1);

namespace Urbania\Directorio\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Urbania\Authorization\Infrastructure\Models\EloquentRoleAssignment;
use Urbania\Directorio\Infrastructure\Http\Requests\PropertyOccupant\StorePropertyOccupantRequest;
use Urbania\Directorio\Infrastructure\Http\Requests\PropertyOccupant\UpdatePropertyOccupantRequest;
use Urbania\Directorio\Infrastructure\Http\Resources\PropertyOccupantResource;
use Urbania\Directorio\Infrastructure\Models\EloquentPropertyOccupant;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;

final readonly class PropertyOccupantController
{
    /**
     * List active occupants of a unit.
     *
     * GET /properties/{property}/occupants
     *
     * R-DIR-01/03: tenant isolation + staff/resident scoping (broader than write access —
     * a resident can see who else occupies their own unit, CA 13).
     */
    public function index(Request $request, string $property): JsonResponse
    {
        if (! $this->canAccessProperty($request, $property)) {
            return $this->notFound();
        }

        $occupants = EloquentPropertyOccupant::query()
            ->where('property_id', $property)
            ->whereNull('deleted_at')
            ->with(['contact', 'occupantType'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => PropertyOccupantResource::collection($occupants),
        ]);
    }

    /**
     * Assign a contact to a unit.
     *
     * POST /properties/{property}/occupants
     *
     * R-DIR-11: unique (contact_id, property_id, occupant_type_id) among active rows → 409.
     * R-DIR-07: marking es_principal=true unmarks any other active principal for the same
     * property_id + occupant_type_id.
     */
    public function store(StorePropertyOccupantRequest $request, string $property): JsonResponse
    {
        if (! $this->canManageProperty($request, $property)) {
            return $this->accessDenied($request);
        }

        $user = $request->user();
        $contactId = (string) $request->validated('contact_id');
        $occupantTypeId = (string) $request->validated('occupant_type_id');
        $esPrincipal = (bool) ($request->validated('es_principal') ?? false);

        $duplicate = EloquentPropertyOccupant::query()
            ->where('contact_id', $contactId)
            ->where('property_id', $property)
            ->where('occupant_type_id', $occupantTypeId)
            ->whereNull('deleted_at')
            ->exists();

        if ($duplicate) {
            return response()->json([
                'error' => [
                    'code' => 'OCCUPANT_ASSIGNMENT_DUPLICATE',
                    'message' => 'Este contacto ya está asignado a esta unidad con ese tipo de ocupante.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        $occupant = DB::transaction(function () use ($property, $contactId, $occupantTypeId, $esPrincipal, $user): EloquentPropertyOccupant {
            if ($esPrincipal) {
                $this->unmarkOtherPrincipals($property, $occupantTypeId, null);
            }

            $occupant = new EloquentPropertyOccupant([
                'contact_id' => $contactId,
                'property_id' => $property,
                'occupant_type_id' => $occupantTypeId,
                'es_principal' => $esPrincipal,
                'created_by' => $user?->id,
            ]);
            $occupant->save();

            return $occupant;
        });

        $occupant->load(['contact', 'occupantType']);

        return (new PropertyOccupantResource($occupant))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an occupant assignment (occupant_type_id and/or es_principal).
     *
     * PATCH /property-occupants/{id}
     */
    public function update(UpdatePropertyOccupantRequest $request, string $id): JsonResponse
    {
        $occupant = $this->findOccupantForManagement($request, $id);

        if ($occupant === null) {
            return $this->accessDenied($request);
        }

        $user = $request->user();
        $newOccupantTypeId = $request->has('occupant_type_id')
            ? (string) $request->validated('occupant_type_id')
            : $occupant->occupant_type_id;
        $newEsPrincipal = $request->has('es_principal')
            ? (bool) $request->validated('es_principal')
            : $occupant->es_principal;

        if ($request->has('occupant_type_id') || $request->has('es_principal')) {
            $duplicate = EloquentPropertyOccupant::query()
                ->where('contact_id', $occupant->contact_id)
                ->where('property_id', $occupant->property_id)
                ->where('occupant_type_id', $newOccupantTypeId)
                ->where('id', '!=', $id)
                ->whereNull('deleted_at')
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'error' => [
                        'code' => 'OCCUPANT_ASSIGNMENT_DUPLICATE',
                        'message' => 'Este contacto ya está asignado a esta unidad con ese tipo de ocupante.',
                        'trace_id' => (string) Str::orderedUuid(),
                    ],
                ], 409);
            }
        }

        DB::transaction(function () use ($occupant, $newOccupantTypeId, $newEsPrincipal, $user, $id): void {
            if ($newEsPrincipal) {
                $this->unmarkOtherPrincipals($occupant->property_id, $newOccupantTypeId, $id);
            }

            $occupant->occupant_type_id = $newOccupantTypeId;
            $occupant->es_principal = $newEsPrincipal;
            $occupant->updated_by = $user?->id;
            $occupant->save();
        });

        return (new PropertyOccupantResource($occupant->fresh(['contact', 'occupantType'])))->response();
    }

    /**
     * Remove an occupant assignment (soft delete).
     *
     * DELETE /property-occupants/{id}
     */
    public function destroy(Request $request, string $id): Response|JsonResponse
    {
        $occupant = $this->findOccupantForManagement($request, $id);

        if ($occupant === null) {
            return $this->accessDenied($request);
        }

        $occupant->delete();

        return response()->noContent();
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    /**
     * Unmark any other active "principal" occupant for the same property + occupant type
     * (R-DIR-07) — must run before saving a new/updated principal row to avoid violating
     * the partial unique index.
     */
    private function unmarkOtherPrincipals(string $propertyId, string $occupantTypeId, ?string $excludeId): void
    {
        $query = EloquentPropertyOccupant::query()
            ->where('property_id', $propertyId)
            ->where('occupant_type_id', $occupantTypeId)
            ->where('es_principal', true)
            ->whereNull('deleted_at');

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        $query->update(['es_principal' => false]);
    }

    /**
     * Find a property occupant by id, scoped to management access on its parent property.
     */
    private function findOccupantForManagement(Request $request, string $id): ?EloquentPropertyOccupant
    {
        $occupant = EloquentPropertyOccupant::query()
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if ($occupant !== null && $this->canManageProperty($request, $occupant->property_id)) {
            return $occupant;
        }

        return null;
    }

    /**
     * Whether the actor can READ occupants of this property — broader than management:
     * includes org, condominium, tower, and unit (resident) scope. Used by index() (CA 13).
     */
    private function canAccessProperty(Request $request, string $id): bool
    {
        $property = $this->findPropertyInOrg($request, $id);

        if ($property === null) {
            return false;
        }

        $scope = $this->getManagementScope($request);

        if ($scope['all']) {
            return true;
        }

        if (in_array($property->condominium_id, $scope['condoIds'], true)) {
            return true;
        }

        if ($property->tower_id !== null && in_array($property->tower_id, $scope['towerIds'], true)) {
            return true;
        }

        // Unit scope (resident) — access to their own unit only.
        $unitIds = $this->getUnitScopeIds($request);

        return in_array($id, $unitIds, true);
    }

    /**
     * Whether the actor can WRITE (assign/edit/remove) occupants of this property — org,
     * condominium, or tower scope only (NOT unit/resident scope, CA 12).
     */
    private function canManageProperty(Request $request, string $id): bool
    {
        $property = $this->findPropertyInOrg($request, $id);

        if ($property === null) {
            return false;
        }

        $scope = $this->getManagementScope($request);

        if ($scope['all']) {
            return true;
        }

        if (in_array($property->condominium_id, $scope['condoIds'], true)) {
            return true;
        }

        return $property->tower_id !== null && in_array($property->tower_id, $scope['towerIds'], true);
    }

    /**
     * Fetch a property by id, scoped only to the actor's organization (tenant isolation) —
     * no staff/resident scope check yet, see canAccessProperty()/canManageProperty().
     */
    private function findPropertyInOrg(Request $request, string $id): ?EloquentProperty
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        return EloquentProperty::query()
            ->where('id', $id)
            ->whereHas('condominium', function ($q) use ($organizationId): void {
                $q->where('organization_id', $organizationId);
            })
            ->first();
    }

    /**
     * CA 10/11/12: distinguish "no management scope at all" (403) from "wrong org/out of
     * scope" (404, anti-enumeration) when denying a write action.
     */
    private function accessDenied(Request $request): JsonResponse
    {
        $scope = $this->getManagementScope($request);

        if (! $scope['all'] && $scope['condoIds'] === [] && $scope['towerIds'] === []) {
            return $this->forbidden('No tiene permisos para gestionar ocupantes.');
        }

        return $this->notFound();
    }

    /**
     * @return array{all: bool, condoIds: string[], towerIds: string[]}
     */
    private function getManagementScope(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return ['all' => false, 'condoIds' => [], 'towerIds' => []];
        }

        $assignments = EloquentRoleAssignment::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->get();

        $hasOrgScope = $assignments->contains(
            fn (EloquentRoleAssignment $a): bool => $a->scope_type === 'organization',
        );

        if ($hasOrgScope) {
            return ['all' => true, 'condoIds' => [], 'towerIds' => []];
        }

        $condoIds = $assignments
            ->filter(fn (EloquentRoleAssignment $a): bool => $a->scope_type === 'condominium' && $a->scope_id !== null)
            ->pluck('scope_id')
            ->unique()
            ->values()
            ->toArray();

        $towerIds = $assignments
            ->filter(fn (EloquentRoleAssignment $a): bool => $a->scope_type === 'tower' && $a->scope_id !== null)
            ->pluck('scope_id')
            ->unique()
            ->values()
            ->toArray();

        return ['all' => false, 'condoIds' => $condoIds, 'towerIds' => $towerIds];
    }

    /**
     * @return string[]
     */
    private function getUnitScopeIds(Request $request): array
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

    private function notFound(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'PROPERTY_NOT_FOUND',
                'message' => 'Unidad no encontrada.',
                'trace_id' => (string) Str::orderedUuid(),
            ],
        ], 404);
    }
}
