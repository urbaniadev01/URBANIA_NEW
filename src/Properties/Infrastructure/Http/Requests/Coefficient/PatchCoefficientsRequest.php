<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Http\Requests\Coefficient;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PatchCoefficientsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.property_id' => ['required', 'uuid', 'exists:properties,id'],
            'items.*.tipo' => ['required', 'string', 'in:copropiedad,parqueadero,deposito,mantenimiento'],
            'items.*.valor' => ['required', 'numeric', 'min:0', 'max:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'El arreglo de coeficientes es obligatorio.',
            'items.array' => 'El cuerpo debe ser un arreglo de coeficientes.',
            'items.min' => 'Debe enviar al menos un coeficiente.',
            'items.*.property_id.required' => 'El ID de la unidad es obligatorio para cada coeficiente.',
            'items.*.property_id.uuid' => 'El ID de la unidad debe ser un UUID válido.',
            'items.*.property_id.exists' => 'Una de las unidades especificadas no existe.',
            'items.*.tipo.required' => 'El tipo de coeficiente es obligatorio.',
            'items.*.tipo.in' => 'El tipo de coeficiente no es válido. Los tipos permitidos son: copropiedad, parqueadero, deposito, mantenimiento.',
            'items.*.valor.required' => 'El valor del coeficiente es obligatorio.',
            'items.*.valor.numeric' => 'El valor del coeficiente debe ser numérico.',
            'items.*.valor.min' => 'El valor del coeficiente debe ser mayor o igual a 0.',
            'items.*.valor.max' => 'El valor del coeficiente debe ser menor o igual a 1.',
        ];
    }

    /**
     * Override failed validation to return the project's standard error format.
     *
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();

        // Detect specific coefficient errors for better error codes
        if ($errors->has('items.*.tipo')) {
            throw new ValidationException(
                $validator,
                response()->json([
                    'error' => [
                        'code' => 'COEFFICIENT_INVALID_TYPE',
                        'message' => 'El tipo de coeficiente no es válido. Los tipos permitidos son: copropiedad, parqueadero, deposito, mantenimiento.',
                        'trace_id' => request()->header('X-Trace-Id', (string) Str::orderedUuid()),
                    ],
                ], 422),
            );
        }

        if ($errors->has('items.*.valor')) {
            throw new ValidationException(
                $validator,
                response()->json([
                    'error' => [
                        'code' => 'COEFFICIENT_OUT_OF_RANGE',
                        'message' => 'El valor del coeficiente debe estar entre 0 y 1.',
                        'trace_id' => request()->header('X-Trace-Id', (string) Str::orderedUuid()),
                    ],
                ], 422),
            );
        }

        $firstError = $errors->first();

        throw new ValidationException(
            $validator,
            response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $firstError ?? 'La solicitud no pasó la validación.',
                    'trace_id' => request()->header('X-Trace-Id', (string) Str::orderedUuid()),
                ],
            ], 422),
        );
    }
}
