<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Http\Resources;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property User $resource
 * @property string $role
 * @property list<string> $permissions
 */
final class MeResource extends JsonResource
{
    /**
     * The wrapper key for the resource.
     */
    public static $wrap = 'user';

    public string $role = 'user';

    public array $permissions = [];

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'email' => $this->resource->email,
            'name' => $this->resource->contact?->nombre,
            'role' => $this->role,
            'permissions' => $this->permissions,
        ];
    }
}
