<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyType;

/**
 * @property EloquentPropertyType $resource
 */
final class PropertyTypeResource extends JsonResource
{
    /**
     * The wrapper key for the resource — debe ser 'data' para respetar
     * LOCK-PROPIEDADES-01 (ver _state/contracts/CONTRACT_LOCKS.md), que index()/show()
     * ya aplican manualmente. No cambiar sin actualizar el contrato congelado.
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
