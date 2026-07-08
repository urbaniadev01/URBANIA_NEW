<?php

declare(strict_types=1);

namespace Urbania\Authorization\Infrastructure\Repositories;

use Urbania\Authorization\Domain\Repositories\RoleAssignmentRepositoryInterface;
use Urbania\Authorization\Infrastructure\Models\EloquentRoleAssignment;

final readonly class EloquentRoleAssignmentRepository implements RoleAssignmentRepositoryInterface
{
    public function findByUserId(string $userId): array
    {
        return EloquentRoleAssignment::where('user_id', $userId)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get()
            ->all();
    }

    public function save(EloquentRoleAssignment $assignment): void
    {
        $assignment->save();
    }

    public function delete(EloquentRoleAssignment $assignment): void
    {
        $assignment->delete();
    }
}
