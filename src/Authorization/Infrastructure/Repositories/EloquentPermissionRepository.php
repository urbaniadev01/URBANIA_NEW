<?php

declare(strict_types=1);

namespace Urbania\Authorization\Infrastructure\Repositories;

use Urbania\Authorization\Domain\Repositories\PermissionRepositoryInterface;
use Urbania\Authorization\Infrastructure\Models\EloquentPermission;

final readonly class EloquentPermissionRepository implements PermissionRepositoryInterface
{
    public function findAll(): array
    {
        return EloquentPermission::all()->all();
    }

    public function findByNames(array $names): array
    {
        return EloquentPermission::whereIn('name', $names)->get()->all();
    }
}
