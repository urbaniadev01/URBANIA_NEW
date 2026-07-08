<?php

declare(strict_types=1);

namespace Urbania\Authorization\Domain\Repositories;

use Urbania\Authorization\Infrastructure\Models\EloquentRole;

interface RoleRepositoryInterface
{
    public function findById(string $id): ?EloquentRole;

    public function findByName(string $name): ?EloquentRole;
}
