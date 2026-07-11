<?php

declare(strict_types=1);

namespace Urbania\Billing\Infrastructure\Http\Requests\ChargeConcept;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StoreChargeConceptRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', 'in:administracion,fondo_imprevistos,multa,extraordinaria'],
            'metodo_calculo' => ['required', 'string', 'in:coeficiente,fijo,por_area,manual'],
            'valor_base' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del concepto de cobro es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder los 255 caracteres.',
            'tipo.required' => 'El tipo de concepto es obligatorio.',
            'tipo.in' => 'El tipo debe ser uno de: administracion, fondo_imprevistos, multa, extraordinaria.',
            'metodo_calculo.required' => 'El método de cálculo es obligatorio.',
            'metodo_calculo.in' => 'El método de cálculo debe ser uno de: coeficiente, fijo, por_area, manual.',
            'valor_base.required' => 'El valor base es obligatorio.',
            'valor_base.numeric' => 'El valor base debe ser numérico.',
            'valor_base.min' => 'El valor base no puede ser negativo.',
        ];
    }

    /**
     * Override failed validation to return the project's standard error format.
     *
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        $firstError = $validator->errors()->first();

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
