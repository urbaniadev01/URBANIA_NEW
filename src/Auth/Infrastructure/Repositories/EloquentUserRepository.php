<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Repositories;

use App\Models\User;
use Urbania\Auth\Domain\Repositories\UserRepositoryInterface;

final readonly class EloquentUserRepository implements UserRepositoryInterface
{
    /**
     * @param array{organization_id: string, email: string, password_hash: string, estado: string} $data
     */
    public function create(array $data): User
    {
        $user = new User($data);
        $user->save();

        return $user;
    }

    public function existsByEmail(string $email): bool
    {
        return User::where('email', $email)->exists();
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findById(string $id): ?User
    {
        return User::where('id', $id)->first();
    }
}
