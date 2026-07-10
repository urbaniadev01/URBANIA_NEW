<?php

declare(strict_types=1);

namespace Urbania\Auth\Application\DTOs;

final readonly class ResetPasswordRequestDto
{
    public function __construct(
        public string $token,
        public string $password,
        public string $passwordConfirmation,
    ) {}
}
