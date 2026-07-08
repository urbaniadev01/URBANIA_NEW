<?php

declare(strict_types=1);

namespace Urbania\Auth\Domain\Repositories;

use Urbania\Auth\Infrastructure\Models\EloquentRefreshToken;

interface RefreshTokenRepositoryInterface
{
    public function findByJti(string $jti): ?EloquentRefreshToken;

    /**
     * @param array{user_id: string, jti: string, estado: string, expires_at: string} $data
     */
    public function create(array $data): EloquentRefreshToken;

    public function invalidateByJti(string $jti): void;

    public function invalidateAllByUserId(string $userId): void;
}
