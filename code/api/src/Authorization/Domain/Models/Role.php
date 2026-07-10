<?php

declare(strict_types=1);

namespace Urbania\Authorization\Domain\Models;

final readonly class Role
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description = null,
    ) {}
}
