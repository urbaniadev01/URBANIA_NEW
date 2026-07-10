<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Urbania\Properties\Infrastructure\Models\EloquentTower;

/**
 * @property EloquentTower $resource
 */
final class TowerResource extends JsonResource
{
    /**
     * The wrapper key for the resource.
     */
    public static $wrap = 'tower';

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'condominium_id' => $this->resource->condominium_id,
            'nombre' => $this->resource->nombre,
            'created_by' => $this->resource->created_by,
            'updated_by' => $this->resource->updated_by,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
