<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;

/**
 * @property EloquentProperty $resource
 *
 * Resource for property detail (show) — R-10: area_m2 IS exposed here.
 */
final class PropertyResource extends JsonResource
{
    /**
     * The wrapper key for the resource.
     */
    public static $wrap = 'property';

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $data = [
            'id' => $this->resource->id,
            'condominium_id' => $this->resource->condominium_id,
            'tower_id' => $this->resource->tower_id,
            'property_type_id' => $this->resource->property_type_id,
            'property_status_id' => $this->resource->property_status_id,
            'codigo' => $this->resource->codigo,
            'piso' => $this->resource->piso,
            'area_m2' => $this->resource->area_m2,
            'created_by' => $this->resource->created_by,
            'updated_by' => $this->resource->updated_by,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];

        // Include nested relations when loaded
        if ($this->resource->relationLoaded('type')) {
            $data['type'] = new PropertyTypeResource($this->resource->type);
        }

        if ($this->resource->relationLoaded('status')) {
            $data['status'] = new PropertyStatusResource($this->resource->status);
        }

        if ($this->resource->relationLoaded('tower')) {
            $data['tower'] = new TowerResource($this->resource->tower);
        }

        if ($this->resource->relationLoaded('condominium')) {
            $data['condominium'] = new CondominiumResource($this->resource->condominium);
        }

        return $data;
    }
}
