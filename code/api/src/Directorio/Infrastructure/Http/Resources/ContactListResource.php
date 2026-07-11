<?php

declare(strict_types=1);

namespace Urbania\Directorio\Infrastructure\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Urbania\Auth\Infrastructure\Models\EloquentContact;

/**
 * @property EloquentContact $resource
 *
 * Contact list (index) — R-DIR-06 habeas data: email/telefono are only included when the
 * request has been flagged with full management access (organization-level scope). Staff
 * scoped to a condominium/tower see nombre only.
 */
final class ContactListResource extends JsonResource
{
    /**
     * The wrapper key for the resource.
     */
    public static $wrap = 'data';

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $showSensitive = (bool) $request->attributes->get('contacts_show_sensitive', false);

        $data = [
            'id' => $this->resource->id,
            'organization_id' => $this->resource->organization_id,
            'nombre' => $this->resource->nombre,
            'created_by' => $this->resource->created_by,
            'updated_by' => $this->resource->updated_by,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];

        if ($showSensitive) {
            $data['email'] = $this->resource->email;
            $data['telefono'] = $this->resource->telefono;
        }

        return $data;
    }
}
