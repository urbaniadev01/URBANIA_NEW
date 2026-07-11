<?php

declare(strict_types=1);

namespace Urbania\Directorio\Infrastructure\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Urbania\Auth\Infrastructure\Models\EloquentContact;

/**
 * @property EloquentContact $resource
 *
 * Full contact detail — used for show/store/update and /me/contact.
 * Always includes email/telefono (R-DIR-06 habeas data only restricts list views, see
 * ContactListResource).
 */
final class ContactResource extends JsonResource
{
    /**
     * The wrapper key for the resource — debe ser 'data' para respetar
     * LOCK-DIRECTORIO-02 (ver _state/contracts/CONTRACT_LOCKS.md).
     */
    public static $wrap = 'data';

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'organization_id' => $this->resource->organization_id,
            'user_id' => $this->resource->user_id,
            'nombre' => $this->resource->nombre,
            'email' => $this->resource->email,
            'telefono' => $this->resource->telefono,
            'created_by' => $this->resource->created_by,
            'updated_by' => $this->resource->updated_by,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
