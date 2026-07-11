<?php

declare(strict_types=1);

namespace Urbania\Directorio\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Urbania\Auth\Infrastructure\Models\EloquentContact;
use Urbania\Directorio\Infrastructure\Http\Requests\Contact\UpdateContactRequest;
use Urbania\Directorio\Infrastructure\Http\Resources\ContactResource;

/**
 * Self-service access to the authenticated user's own contact (R-DIR-04).
 * No role_assignment/permission required — any authenticated user can read/edit
 * their own contact data.
 */
final readonly class MeContactController
{
    /**
     * GET /me/contact
     */
    public function show(Request $request): JsonResponse
    {
        $contact = $this->findOwnContact($request);

        if ($contact === null) {
            return $this->notFound();
        }

        return (new ContactResource($contact))->response();
    }

    /**
     * PATCH /me/contact
     */
    public function update(UpdateContactRequest $request): JsonResponse
    {
        $contact = $this->findOwnContact($request);

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
     * Criterion 16: defensive — the ADR-001 invariant guarantees every active user
     * has a contact, but this endpoint does not assume it silently.
     */
    private function findOwnContact(Request $request): ?EloquentContact
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return EloquentContact::query()
            ->where('user_id', $user->id)
            ->first();
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'CONTACT_NOT_FOUND',
                'message' => 'No se encontró un contacto asociado a este usuario.',
                'trace_id' => (string) Str::orderedUuid(),
            ],
        ], 404);
    }
}
