<?php

declare(strict_types=1);

namespace Urbania\Billing\Infrastructure\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Urbania\Billing\Infrastructure\Models\EloquentChargeConcept;

/**
 * @property EloquentChargeConcept $resource
 */
final class ChargeConceptResource extends JsonResource
{
    /**
     * The wrapper key for the resource — 'data', consistente con LOCK-PROPIEDADES-01 y el
     * resto de resources del API (ver _state/contracts/CONTRACT_LOCKS.md).
     */
    public static $wrap = 'data';

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var float|int|string|null $rawValorBase */
        $rawValorBase = $this->resource->valor_base;

        return [
            'id' => $this->resource->id,
            'condominium_id' => $this->resource->condominium_id,
            'nombre' => $this->resource->nombre,
            'tipo' => $this->resource->tipo,
            'metodo_calculo' => $this->resource->metodo_calculo,
            'valor_base' => (float) $rawValorBase,
            'activo' => $this->resource->activo,
            'created_by' => $this->resource->created_by,
            'updated_by' => $this->resource->updated_by,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
