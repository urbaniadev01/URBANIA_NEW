<?php

declare(strict_types=1);

namespace Urbania\Directorio\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Urbania\Auth\Infrastructure\Models\EloquentContact;
use Urbania\Authorization\Infrastructure\Models\EloquentRoleAssignment;
use Urbania\Directorio\Infrastructure\Http\Requests\Contact\StoreContactRequest;
use Urbania\Directorio\Infrastructure\Http\Requests\Contact\UpdateContactRequest;
use Urbania\Directorio\Infrastructure\Http\Resources\ContactListResource;
use Urbania\Directorio\Infrastructure\Http\Resources\ContactResource;
use Urbania\Directorio\Infrastructure\Models\EloquentPropertyOccupant;
use Urbania\Properties\Infrastructure\Http\Resources\PropertyListResource;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;

final readonly class ContactController
{
    /**
     * List contacts for the user's organization, scoped by staff assignment.
     *
     * R-DIR-01: Tenant isolation.
     * R-DIR-03: Staff scoping (condominium/tower) — only contacts with an active
     * occupation inside the user's scope. Users with no org/condo/tower scope (e.g.
     * pure residents) get 403 — this is an administrative listing, not self-service.
     * R-DIR-06: Habeas data — email/telefono hidden unless the actor has full
     * (organization-level) management scope.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        $scope = $this->getManagementScope($request);

        if (! $scope['all'] && $scope['condoIds'] === [] && $scope['towerIds'] === []) {
            return $this->forbidden('No tiene permisos para listar contactos.');
        }

        $query = EloquentContact::query()
            ->where('organization_id', $organizationId);

        if (! $scope['all']) {
            $condoIds = $scope['condoIds'];
            $towerIds = $scope['towerIds'];

            $query->whereHas('occupations', function ($q) use ($condoIds, $towerIds): void {
                $q->whereNull('deleted_at')
                    ->whereHas('property', function ($q2) use ($condoIds, $towerIds): void {
                        $q2->where(function ($qq) use ($condoIds, $towerIds): void {
                            if ($condoIds !== []) {
                                $qq->orWhereIn('condominium_id', $condoIds);
                            }
                            if ($towerIds !== []) {
                                $qq->orWhereIn('tower_id', $towerIds);
                            }
                        });
                    });
            });
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where('nombre', 'ilike', "%{$search}%");
        }

        $limit = min($request->integer('limit', 15), 50);
        $cursor = $request->string('cursor')->toString();

        if ($cursor !== '') {
            $query->where('id', '>', $cursor);
        }

        $query->orderBy('id');

        $contacts = $query->limit($limit + 1)->get();

        $hasMore = $contacts->count() > $limit;
        $results = $hasMore ? $contacts->slice(0, $limit) : $contacts;
        $nextCursor = $hasMore ? $results->last()?->id : null;

        // R-DIR-06: only org-level (full management) scope sees email/telefono in listing.
        $request->attributes->set('contacts_show_sensitive', $scope['all']);

        return response()->json([
            'data' => ContactListResource::collection($results),
            'meta' => [
                'next_cursor' => $nextCursor,
            ],
        ]);
    }

    /**
     * Create a new contact for the user's organization. Always without a user_id —
     * a contact WITH login is only ever created via the AUTH invitation flow.
     */
    public function store(StoreContactRequest $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        $contact = new EloquentContact([
            'organization_id' => $organizationId,
            'user_id' => null,
            'nombre' => (string) $request->validated('nombre'),
            'email' => $request->validated('email') ?? null,
            'telefono' => $request->validated('telefono') ?? null,
            'created_by' => $user?->id,
        ]);
        $contact->save();

        return (new ContactResource($contact))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single contact with full detail (scoped by tenant + staff assignment).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $contact = $this->findForTenantWithScope($request, $id);

        if ($contact === null) {
            return $this->notFound();
        }

        return (new ContactResource($contact))->response();
    }

    /**
     * Update a contact.
     */
    public function update(UpdateContactRequest $request, string $id): JsonResponse
    {
        $contact = $this->findForTenantWithScope($request, $id);

        if ($contact === null) {
            return $this->notFound();
        }

        $user = $request->user();

        if ($request->has('nombre')) {
            $contact->nombre = (string) $request->validated('nombre');
        }

        if ($request->has('email')) {
            $contact->email = $request->validated('email');
        }

        if ($request->has('telefono')) {
            $contact->telefono = $request->validated('telefono');
        }

        $contact->updated_by = $user?->id;
        $contact->save();

        return (new ContactResource($contact->fresh()))->response();
    }

    /**
     * Delete a contact (soft delete).
     *
     * R-DIR-08: Cannot delete if it has active occupations.
     */
    public function destroy(Request $request, string $id): Response|JsonResponse
    {
        $contact = $this->findForTenantWithScope($request, $id);

        if ($contact === null) {
            return $this->notFound();
        }

        $hasOccupations = EloquentPropertyOccupant::query()
            ->where('contact_id', $id)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasOccupations) {
            return response()->json([
                'error' => [
                    'code' => 'CONTACT_HAS_OCCUPATIONS',
                    'message' => 'No se puede eliminar el contacto porque tiene ocupaciones activas.',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        $contact->delete();

        return response()->noContent();
    }

    /**
     * List the units (properties) a contact currently occupies.
     *
     * GET /contacts/{id}/properties
     */
    public function properties(Request $request, string $id): JsonResponse
    {
        $contact = $this->findForTenantWithScope($request, $id);

        if ($contact === null) {
            return $this->notFound();
        }

        $propertyIds = EloquentPropertyOccupant::query()
            ->where('contact_id', $id)
            ->whereNull('deleted_at')
            ->pluck('property_id')
            ->unique()
            ->values();

        $properties = EloquentProperty::query()
            ->whereIn('id', $propertyIds)
            ->orderBy('codigo')
            ->get();

        return response()->json([
            'data' => PropertyListResource::collection($properties),
        ]);
    }

    // ---------------------------------------------------------------
    // Private helpers — scoping and authorization
    // ---------------------------------------------------------------

    /**
     * Find a contact by id, scoped to the user's tenant and staff assignment.
     *
     * R-DIR-01: Tenant isolation.
     * R-DIR-03: Staff scoping — contact must have an active occupation within scope,
     * unless the actor has organization-level (full) scope.
     * Anti-enumeration: returns null (mapped to 404) for out-of-scope contacts.
     */
    private function findForTenantWithScope(Request $request, string $id): ?EloquentContact
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        $contact = EloquentContact::query()
            ->where('id', $id)
            ->where('organization_id', $organizationId)
            ->first();

        if ($contact === null) {
            return null;
        }

        $scope = $this->getManagementScope($request);

        if ($scope['all']) {
            return $contact;
        }

        $condoIds = $scope['condoIds'];
        $towerIds = $scope['towerIds'];

        if ($condoIds === [] && $towerIds === []) {
            return null;
        }

        $hasScopedOccupation = EloquentPropertyOccupant::query()
            ->where('contact_id', $id)
            ->whereNull('deleted_at')
            ->whereHas('property', function ($q) use ($condoIds, $towerIds): void {
                $q->where(function ($qq) use ($condoIds, $towerIds): void {
                    if ($condoIds !== []) {
                        $qq->orWhereIn('condominium_id', $condoIds);
                    }
                    if ($towerIds !== []) {
                        $qq->orWhereIn('tower_id', $towerIds);
                    }
                });
            })
            ->exists();

        return $hasScopedOccupation ? $contact : null;
    }

    /**
     * Get the user's effective scope for contact management.
     *
     * Returns:
     *   - ['all' => true, 'condoIds' => [], 'towerIds' => []] for organization-level scope
     *   - ['all' => false, 'condoIds' => [...], 'towerIds' => [...]] for condo/tower-scoped staff
     *   - ['all' => false, 'condoIds' => [], 'towerIds' => []] for users with no management scope
     *
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
     * Return a standard 404 not found response (anti-enumeration: same shape
     * whether the contact doesn't exist, belongs to another org, or is out of scope).
     */
    private function notFound(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'CONTACT_NOT_FOUND',
                'message' => 'Contacto no encontrado.',
                'trace_id' => (string) Str::orderedUuid(),
            ],
        ], 404);
    }
}
