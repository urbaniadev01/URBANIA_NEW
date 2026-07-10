<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyCoefficient;

/**
 * @property EloquentPropertyCoefficient $resource
 */
final class PropertyCoefficientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var float|int|string|null $rawValor */
        $rawValor = $this->resource->valor;

        return [
            'id' => $this->resource->id,
            'property_id' => $this->resource->property_id,
            'tipo' => $this->resource->tipo,
            'valor' => (float) $rawValor,
            'vigente_desde' => $this->resource->vigente_desde?->toDateString(),
            'vigente_hasta' => $this->resource->vigente_hasta?->toDateString(),
            'created_by' => $this->resource->created_by,
            'updated_by' => $this->resource->updated_by,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
