<?php

declare(strict_types=1);

namespace Urbania\Authorization\Application\Services;

use Illuminate\Support\Facades\Cache;
use Urbania\Authorization\Domain\Repositories\RoleAssignmentRepositoryInterface;

final readonly class PermissionResolver
{
    private const CACHE_PREFIX = 'user_permissions:';

    private const CACHE_TTL = 300;

    public function __construct(
        private RoleAssignmentRepositoryInterface $roleAssignmentRepository,
    ) {}

    /**
     * Resolve effective permissions for a user.
     *
     * Returns an array of permission entries, each with:
     *   - permission: string (e.g., "admin.access")
     *   - scope_type: string (e.g., "organization")
     *   - scope_id: string|null
     *
     * Results are cached in Redis (fallback to array in testing) and invalidated
     * when role assignments change.
     *
     * @return list<array{permission: string, scope_type: string, scope_id: string|null}>
     */
    public function resolve(string $userId): array
    {
        $cacheKey = self::CACHE_PREFIX.$userId;

        /** @var list<array{permission: string, scope_type: string, scope_id: string|null}>|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $assignments = $this->roleAssignmentRepository->findByUserId($userId);

        $permissions = [];

        foreach ($assignments as $assignment) {
            $role = $assignment->role()->with('permissions')->first();

            if ($role === null) {
                continue;
            }

            foreach ($role->permissions()->get() as $permission) {
                $permissions[] = [
                    'permission' => $permission->name,
                    'scope_type' => $assignment->scope_type,
                    'scope_id' => $assignment->scope_id,
                ];
            }
        }

        Cache::put($cacheKey, $permissions, self::CACHE_TTL);

        return $permissions;
    }

    /**
     * Invalidate the cached permissions for a specific user.
     */
    public function invalidateUserCache(string $userId): void
    {
        Cache::forget(self::CACHE_PREFIX.$userId);
    }
}
