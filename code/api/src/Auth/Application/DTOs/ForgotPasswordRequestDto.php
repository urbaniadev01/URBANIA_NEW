<?php

declare(strict_types=1);

namespace Urbania\Auth\Application\DTOs;

final readonly class ForgotPasswordRequestDto
{
    public function __construct(
        public string $email,
    ) {}
}
