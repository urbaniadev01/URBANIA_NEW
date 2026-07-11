<?php

declare(strict_types=1);

namespace Urbania\Directorio\Infrastructure\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Urbania\Directorio\Infrastructure\Models\EloquentOccupantType;

/**
 * @property EloquentOccupantType $resource
 */
final class OccupantTypeResource extends JsonResource
{
    /**
     * The wrapper key for the resource — debe ser 'data' para respetar
     * LOCK-DIRECTORIO-01 (ver _state/contracts/CONTRACT_LOCKS.md).
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
            'nombre' => $this->resource->nombre,
            'descripcion' => $this->resource->descripcion,
            'created_by' => $this->resource->created_by,
            'updated_by' => $this->resource->updated_by,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
