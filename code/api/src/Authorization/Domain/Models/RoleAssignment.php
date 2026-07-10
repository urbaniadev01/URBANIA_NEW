<?php

declare(strict_types=1);

namespace Urbania\Authorization\Domain\Models;

final readonly class RoleAssignment
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $roleId,
        public string $scopeType,
        public ?string $scopeId,
        public ?string $expiresAt = null,
    ) {}
}
