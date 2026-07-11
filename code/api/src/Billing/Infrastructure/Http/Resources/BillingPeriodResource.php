<?php

declare(strict_types=1);

namespace Urbania\Billing\Infrastructure\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Urbania\Billing\Infrastructure\Models\EloquentBillingPeriod;

/**
 * @property EloquentBillingPeriod $resource
 */
final class BillingPeriodResource extends JsonResource
{
    public static $wrap = 'data';

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'condominium_id' => $this->resource->condominium_id,
            'anio' => $this->resource->anio,
            'mes' => $this->resource->mes,
            'estado' => $this->resource->estado,
            'created_by' => $this->resource->created_by,
            'updated_by' => $this->resource->updated_by,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
