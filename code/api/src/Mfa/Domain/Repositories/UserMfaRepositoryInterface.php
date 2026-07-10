<?php

declare(strict_types=1);

namespace Urbania\Mfa\Domain\Repositories;

use Urbania\Mfa\Domain\Models\UserMfa;

interface UserMfaRepositoryInterface
{
    public function findByUserId(string $userId): ?UserMfa;

    public function existsByUserId(string $userId): bool;

    /**
     * @param array{id: string, user_id: string, totp_secret: string, recovery_codes: string, enabled_at: string} $data
     */
    public function create(array $data): UserMfa;

    /**
     * @param array<int, array{hash: string, used_at: string|null}> $recoveryCodes
     */
    public function updateRecoveryCodes(string $userId, array $recoveryCodes): void;

    public function deleteByUserId(string $userId): void;
}
