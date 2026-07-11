<?php

declare(strict_types=1);

namespace Urbania\Billing\Infrastructure\Http\Requests\BillingPeriod;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UpdateBillingPeriodRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', 'in:cerrado'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'Solo se admite la transición a estado "cerrado" por esta vía.',
        ];
    }

    /**
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
