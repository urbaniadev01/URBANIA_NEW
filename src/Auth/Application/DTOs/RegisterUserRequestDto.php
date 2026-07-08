<?php

declare(strict_types=1);

namespace Urbania\Auth\Application\DTOs;

final readonly class RegisterUserRequestDto
{
    public function __construct(
        public string $invitationToken,
        public string $password,
        public string $name,
        public ?string $phone,
    ) {}
}
