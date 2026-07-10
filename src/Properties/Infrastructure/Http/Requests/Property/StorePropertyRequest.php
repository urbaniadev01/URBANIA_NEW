<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Http\Requests\Property;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StorePropertyRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:255'],
            'tower_id' => ['sometimes', 'nullable', 'uuid', 'exists:towers,id'],
            'property_type_id' => ['required', 'uuid', 'exists:property_types,id'],
            'property_status_id' => ['required', 'uuid', 'exists:property_statuses,id'],
            'piso' => ['sometimes', 'nullable', 'integer'],
            'area_m2' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.required' => 'El código de la unidad es obligatorio.',
            'codigo.max' => 'El código no puede exceder los 255 caracteres.',
            'tower_id.exists' => 'La torre seleccionada no existe.',
            'property_type_id.required' => 'El tipo de propiedad es obligatorio.',
            'property_type_id.exists' => 'El tipo de propiedad seleccionado no existe.',
            'property_status_id.required' => 'El estado de propiedad es obligatorio.',
            'property_status_id.exists' => 'El estado de propiedad seleccionado no existe.',
            'piso.integer' => 'El campo piso debe ser un número entero.',
            'area_m2.numeric' => 'El área debe ser un valor numérico.',
            'area_m2.min' => 'El área no puede ser negativa.',
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
