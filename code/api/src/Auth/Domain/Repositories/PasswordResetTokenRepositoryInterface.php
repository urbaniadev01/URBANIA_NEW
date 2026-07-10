<?php

declare(strict_types=1);

namespace Urbania\Auth\Domain\Repositories;

use Urbania\Auth\Domain\Models\PasswordResetToken;

interface PasswordResetTokenRepositoryInterface
{
    public function create(string $email, string $plainToken, \DateTimeImmutable $expiresAt): PasswordResetToken;

    /**
     * Find a valid (non-expired) token by its SHA-256 hash.
     */
    public function findValidByTokenHash(string $tokenHash): ?PasswordResetToken;

    /**
     * Find any token (including expired) by its SHA-256 hash.
     * Returns null if the token never existed or was deleted.
     */
    public function findByTokenHash(string $tokenHash): ?PasswordResetToken;

    public function delete(PasswordResetToken $token): void;

    public function findLatestValidByEmail(string $email): ?PasswordResetToken;
}
