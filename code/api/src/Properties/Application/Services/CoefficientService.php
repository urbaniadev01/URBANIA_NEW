<?php

declare(strict_types=1);

namespace Urbania\Properties\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Urbania\Properties\Domain\Exceptions\PropertyNotInCondominiumException;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyCoefficient;

final class CoefficientService
{
    /**
     * Apply coefficient updates atomically for a condominium.
     *
     * For each item in the payload:
     * 1. Validates property belongs to the condominium.
     * 2. Closes any existing active coefficient of the same tipo (R-05).
     * 3. Creates a new coefficient with vigente_desde = today.
     *
     * After all operations, validates copropiedad sum ≈ 1.0 (R-06).
     * Returns warnings if sum ≠ 1.0 (non-blocking).
     *
     * @param array<int, array{property_id: string, tipo: string, valor: float}> $items
     * @return array{data: list<EloquentPropertyCoefficient>, warnings: list<array{code: string, detail: array{condominium_id: string, sum: float}}>}
     */
    public function applyCoefficients(string $condominiumId, array $items, string $userId): array
    {
        return DB::transaction(function () use ($condominiumId, $items, $userId) {
            $created = [];

            foreach ($items as $item) {
                $propertyId = $item['property_id'];
                $tipo = $item['tipo'];
                $valor = (float) $item['valor'];

                // Verify property belongs to this condominium
                $property = EloquentProperty::query()
                    ->where('id', $propertyId)
                    ->where('condominium_id', $condominiumId)
                    ->first();

                if ($property === null) {
                    throw new PropertyNotInCondominiumException($propertyId, $condominiumId);
                }

                // R-05: Close any existing active coefficient of the same tipo for this property
                $existingActive = EloquentPropertyCoefficient::query()
                    ->where('property_id', $propertyId)
                    ->where('tipo', $tipo)
                    ->whereNull('vigente_hasta')
                    ->first();

                if ($existingActive !== null) {
                    $existingActive->vigente_hasta = Carbon::today()->subDay()->toDateString();
                    $existingActive->updated_by = $userId;
                    $existingActive->save();
                }

                // Create new coefficient, active from today
                $coefficient = new EloquentPropertyCoefficient([
                    'property_id' => $propertyId,
                    'tipo' => $tipo,
                    'valor' => $valor,
                    'vigente_desde' => Carbon::today()->toDateString(),
                    'vigente_hasta' => null,
                    'created_by' => $userId,
                ]);
                $coefficient->save();

                $created[] = $coefficient;
            }

            // R-06: Validate sum of active copropiedad coefficients for the entire condominium
            $warnings = $this->validateCopropiedadSum($condominiumId);

            return [
                'data' => $created,
                'warnings' => $warnings,
            ];
        });
    }

    /**
     * Validate that the sum of all active copropiedad coefficients for
     * properties in this condominium equals 1.0 (100%).
     *
     * R-06: Non-blocking — returns warnings, never throws.
     *
     * @return list<array{code: string, detail: array{condominium_id: string, sum: float}}>
     */
    private function validateCopropiedadSum(string $condominiumId): array
    {
        /** @var float|int|string|null $rawSum */
        $rawSum = EloquentPropertyCoefficient::query()
            ->where('tipo', 'copropiedad')
            ->whereNull('vigente_hasta')
            ->whereHas('property', function ($q) use ($condominiumId): void {
                $q->where('condominium_id', $condominiumId);
            })
            ->sum('valor');

        $sum = round((float) $rawSum, 4);

        if (abs($sum - 1.0) > 0.0001) {
            return [
                [
                    'code' => 'COEFFICIENT_SUM_MISMATCH',
                    'detail' => [
                        'condominium_id' => $condominiumId,
                        'sum' => $sum,
                    ],
                ],
            ];
        }

        return [];
    }

    /**
     * Get all coefficients (active + historical) for a property.
     *
     * @return list<EloquentPropertyCoefficient>
     */
    public function getCoefficientsForProperty(string $propertyId): array
    {
        return EloquentPropertyCoefficient::query()
            ->where('property_id', $propertyId)
            ->orderBy('tipo')
            ->orderByDesc('vigente_desde')
            ->get()
            ->all();
    }
}
