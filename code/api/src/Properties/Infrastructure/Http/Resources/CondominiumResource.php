<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;

/**
 * @property EloquentCondominium $resource
 */
final class CondominiumResource extends JsonResource
{
    /**
     * The wrapper key for the resource.
     */
    public static $wrap = 'condominium';

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $data = [
            'id' => $this->resource->id,
            'organization_id' => $this->resource->organization_id,
            'nombre' => $this->resource->nombre,
            'direccion' => $this->resource->direccion,
            'nit' => $this->resource->nit,
            'created_by' => $this->resource->created_by,
            'updated_by' => $this->resource->updated_by,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];

        // Conditionally include towers when loaded (e.g., in show endpoint)
        if ($this->resource->relationLoaded('towers')) {
            $data['towers'] = TowerResource::collection($this->resource->towers);
        }

        return $data;
    }
}
