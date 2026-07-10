<?php

declare(strict_types=1);

namespace Urbania\Authorization\Infrastructure\Repositories;

use Urbania\Authorization\Domain\Repositories\RoleRepositoryInterface;
use Urbania\Authorization\Infrastructure\Models\EloquentRole;

final readonly class EloquentRoleRepository implements RoleRepositoryInterface
{
    public function findById(string $id): ?EloquentRole
    {
        return EloquentRole::find($id);
    }

    public function findByName(string $name): ?EloquentRole
    {
        return EloquentRole::where('name', $name)->first();
    }
}
