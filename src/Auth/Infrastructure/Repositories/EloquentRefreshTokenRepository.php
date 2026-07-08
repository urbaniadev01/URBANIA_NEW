<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Repositories;

use Urbania\Auth\Domain\Repositories\RefreshTokenRepositoryInterface;
use Urbania\Auth\Infrastructure\Models\EloquentRefreshToken;

final readonly class EloquentRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function findByJti(string $jti): ?EloquentRefreshToken
    {
        return EloquentRefreshToken::where('jti', $jti)->first();
    }

    /**
     * @param array{user_id: string, jti: string, estado: string, expires_at: string} $data
     */
    public function create(array $data): EloquentRefreshToken
    {
        $token = new EloquentRefreshToken($data);
        $token->save();

        return $token;
    }

    public function invalidateByJti(string $jti): void
    {
        EloquentRefreshToken::where('jti', $jti)->update(['estado' => 'invalidado']);
    }

    public function invalidateAllByUserId(string $userId): void
    {
        EloquentRefreshToken::where('user_id', $userId)->update(['estado' => 'invalidado']);
    }
}
