<?php

declare(strict_types=1);

namespace Urbania\Authorization\Domain\Repositories;

use Urbania\Authorization\Infrastructure\Models\EloquentRoleAssignment;

interface RoleAssignmentRepositoryInterface
{
    /**
     * Get all role assignments for a user.
     *
     * @return list<EloquentRoleAssignment>
     */
    public function findByUserId(string $userId): array;

    public function save(EloquentRoleAssignment $assignment): void;

    public function delete(EloquentRoleAssignment $assignment): void;
}
