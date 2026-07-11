<?php

declare(strict_types=1);

namespace Urbania\Billing\Infrastructure\Http\Requests\BillingPeriod;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StoreBillingPeriodRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'anio.required' => 'El año es obligatorio.',
            'mes.required' => 'El mes es obligatorio.',
            'mes.min' => 'El mes debe estar entre 1 y 12.',
            'mes.max' => 'El mes debe estar entre 1 y 12.',
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
