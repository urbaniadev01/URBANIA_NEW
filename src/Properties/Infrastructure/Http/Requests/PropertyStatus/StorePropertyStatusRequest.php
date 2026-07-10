<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Http\Requests\PropertyStatus;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StorePropertyStatusRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del estado de propiedad es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder los 255 caracteres.',
            'descripcion.max' => 'La descripción no puede exceder los 1000 caracteres.',
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
