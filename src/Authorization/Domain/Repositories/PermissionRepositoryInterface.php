<?php

declare(strict_types=1);

namespace Urbania\Authorization\Domain\Repositories;

use Urbania\Authorization\Infrastructure\Models\EloquentPermission;

interface PermissionRepositoryInterface
{
    /**
     * @return list<EloquentPermission>
     */
    public function findAll(): array;

    /**
     * @return list<EloquentPermission>
     */
    public function findByNames(array $names): array;
}
