<?php

declare(strict_types=1);

namespace Urbania\Auth\Domain\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    public function create(array $data): User;

    public function existsByEmail(string $email): bool;

    public function findByEmail(string $email): ?User;

    public function findById(string $id): ?User;
}
