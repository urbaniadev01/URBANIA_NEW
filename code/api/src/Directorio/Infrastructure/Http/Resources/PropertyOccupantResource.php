<?php

declare(strict_types=1);

namespace Urbania\Directorio\Infrastructure\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Urbania\Directorio\Infrastructure\Models\EloquentPropertyOccupant;

/**
 * @property EloquentPropertyOccupant $resource
 *
 * R-DIR-06: the nested contact is always minimal (id + nombre) — this endpoint never exposes
 * email/telefono, regardless of actor. Full contact detail lives behind GET /contacts/{id},
 * which has its own permission checks (DIRECTORIO-B03).
 */
final class PropertyOccupantResource extends JsonResource
{
    /**
     * The wrapper key for the resource — debe ser 'data' para respetar
     * LOCK-DIRECTORIO-03 (ver _state/contracts/CONTRACT_LOCKS.md).
     */
    public static $wrap = 'data';

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $data = [
            'id' => $this->resource->id,
            'property_id' => $this->resource->property_id,
            'contact_id' => $this->resource->contact_id,
            'occupant_type_id' => $this->resource->occupant_type_id,
            'es_principal' => $this->resource->es_principal,
            'created_by' => $this->resource->created_by,
            'updated_by' => $this->resource->updated_by,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];

        if ($this->resource->relationLoaded('contact') && $this->resource->contact !== null) {
            $data['contact'] = [
                'id' => $this->resource->contact->id,
                'nombre' => $this->resource->contact->nombre,
            ];
        }

        if ($this->resource->relationLoaded('occupantType') && $this->resource->occupantType !== null) {
            $data['occupant_type'] = new OccupantTypeResource($this->resource->occupantType);
        }

        return $data;
    }
}
