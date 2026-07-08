<?php

declare(strict_types=1);

namespace Urbania\Authorization\Application\UseCases;

use Urbania\Authorization\Application\Services\PermissionResolver;

final readonly class CheckPermissionUseCase
{
    public function __construct(
        private PermissionResolver $permissionResolver,
    ) {}

    /**
     * Check if a user has a specific permission for a given scope.
     *
     * The scope hierarchy is: organization > condominium > tower > unit.
     * A permission granted at a broader scope applies to all narrower scopes
     * within that organization. This is validated by the fact that scope_type
     * and scope_id must match — or if checking a narrower scope, a broader
     * scope assignment also satisfies it (e.g., admin at organization level
     * can access everything in that org).
     *
     * @param string $userId The authenticated user ID.
     * @param string $permission The permission name (e.g., "admin.access").
     * @param string $scopeType The scope type being accessed (e.g., "organization").
     * @param string|null $scopeId The scope ID being accessed.
     */
    public function execute(string $userId, string $permission, string $scopeType, ?string $scopeId = null): bool
    {
        $effectivePermissions = $this->permissionResolver->resolve($userId);

        foreach ($effectivePermissions as $entry) {
            // Permission name must match
            if ($entry['permission'] !== $permission) {
                continue;
            }

            // Scope type must match
            if ($entry['scope_type'] !== $scopeType) {
                continue;
            }

            // Scope ID: if the assignment has a null scope_id, it's global for that scope_type
            if ($entry['scope_id'] === null) {
                return true;
            }

            // Exact scope_id match
            if ($entry['scope_id'] === $scopeId) {
                return true;
            }
        }

        return false;
    }
}
