<?php

declare(strict_types=1);

namespace Urbania\Directorio\Infrastructure\Http\Requests\PropertyOccupant;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Urbania\Auth\Infrastructure\Models\EloquentContact;
use Urbania\Directorio\Infrastructure\Models\EloquentOccupantType;

final class StorePropertyOccupantRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'uuid'],
            'occupant_type_id' => ['required', 'uuid'],
            'es_principal' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_id.required' => 'El contacto es obligatorio.',
            'contact_id.uuid' => 'El contacto no es válido.',
            'occupant_type_id.required' => 'El tipo de ocupante es obligatorio.',
            'occupant_type_id.uuid' => 'El tipo de ocupante no es válido.',
        ];
    }

    /**
     * Criterion 5: contact_id/occupant_type_id that don't belong to the actor's
     * organization (or the system catalog, for occupant_type_id) fail as 422
     * VALIDATION_ERROR — not a 404/403 domain error, per the card.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $organizationId = $this->user()?->organization_id;

            $contactId = $this->input('contact_id');
            if (is_string($contactId) && $contactId !== '') {
                $contactExists = EloquentContact::query()
                    ->where('id', $contactId)
                    ->where('organization_id', $organizationId)
                    ->exists();

                if (! $contactExists) {
                    $validator->errors()->add('contact_id', 'El contacto no pertenece a la organización del actor.');
                }
            }

            $occupantTypeId = $this->input('occupant_type_id');
            if (is_string($occupantTypeId) && $occupantTypeId !== '') {
                $occupantTypeExists = EloquentOccupantType::query()
                    ->where('id', $occupantTypeId)
                    ->where(function ($q) use ($organizationId): void {
                        $q->whereNull('organization_id');
                        if ($organizationId !== null) {
                            $q->orWhere('organization_id', $organizationId);
                        }
                    })
                    ->exists();

                if (! $occupantTypeExists) {
                    $validator->errors()->add('occupant_type_id', 'El tipo de ocupante no existe o no pertenece a la organización del actor.');
                }
            }
        });
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
