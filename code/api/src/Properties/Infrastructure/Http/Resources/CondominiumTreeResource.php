<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;

/**
 * @property EloquentCondominium $resource
 *
 * Resource for the condominium tree endpoint.
 * Shows: condominium → towers → unit counts.
 */
final class CondominiumTreeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $towers = $this->resource->towers()
            ->withCount(['properties' => fn ($q) => $q->whereNull('deleted_at')])
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->get();

        $towerNodes = $towers->map(function ($tower) {
            return [
                'id' => $tower->id,
                'nombre' => $tower->nombre,
                'properties_count' => $tower->properties_count,
            ];
        })->values()->toArray();

        // Count properties without a tower (directly under condominium)
        $untoweredCount = $this->resource->properties()
            ->whereNull('tower_id')
            ->whereNull('deleted_at')
            ->count();

        return [
            'id' => $this->resource->id,
            'nombre' => $this->resource->nombre,
            'organization_id' => $this->resource->organization_id,
            'towers' => $towerNodes,
            'untowered_properties_count' => $untoweredCount,
        ];
    }
}
