<?php

declare(strict_types=1);

namespace Urbania\Billing\Infrastructure\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Urbania\Billing\Infrastructure\Models\EloquentBillingRun;

/**
 * @property EloquentBillingRun $resource
 */
final class BillingRunResource extends JsonResource
{
    public static $wrap = 'data';

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'billing_period_id' => $this->resource->billing_period_id,
            'ejecutado_por' => $this->resource->ejecutado_por,
            'fecha' => $this->resource->fecha?->toIso8601String(),
            'estado' => $this->resource->estado,
            // R-COB (decisión 8): resumen solo se expone cuando la corrida terminó
            // (completado/fallido) — mientras está en_proceso el Job todavía lo está armando.
            'resumen' => $this->resource->estado !== 'en_proceso' ? $this->resource->resumen : null,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
