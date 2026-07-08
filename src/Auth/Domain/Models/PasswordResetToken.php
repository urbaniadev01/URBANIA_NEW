<?php

declare(strict_types=1);

namespace Urbania\Auth\Domain\Models;

final readonly class PasswordResetToken
{
    public function __construct(
        public string $id,
        public string $email,
        public string $tokenHash,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {}
}
