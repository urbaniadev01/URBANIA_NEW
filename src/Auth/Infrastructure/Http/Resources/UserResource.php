<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Http\Resources;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property User $resource
 * @property string|null $contactName
 */
final class UserResource extends JsonResource
{
    public ?string $contactName = null;

    /**
     * The wrapper key for the resource.
     */
    public static $wrap = 'user';

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'email' => $this->resource->email,
            'name' => $this->contactName ?? $this->resource->contact?->nombre,
            'estado' => $this->resource->estado,
            'organization_id' => $this->resource->organization_id,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
