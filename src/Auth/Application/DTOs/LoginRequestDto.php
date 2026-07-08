<?php

declare(strict_types=1);

namespace Urbania\Auth\Application\DTOs;

final readonly class LoginRequestDto
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
