<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Repositories;

use Urbania\Auth\Domain\Models\PasswordResetToken;
use Urbania\Auth\Domain\Repositories\PasswordResetTokenRepositoryInterface;
use Urbania\Auth\Infrastructure\Models\EloquentPasswordResetToken;

final readonly class EloquentPasswordResetTokenRepository implements PasswordResetTokenRepositoryInterface
{
    public function create(string $email, string $plainToken, \DateTimeImmutable $expiresAt): PasswordResetToken
    {
        $eloquent = new EloquentPasswordResetToken([
            'email' => $email,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt,
        ]);
        $eloquent->save();

        return $this->toDomain($eloquent);
    }

    public function findValidByTokenHash(string $tokenHash): ?PasswordResetToken
    {
        $eloquent = EloquentPasswordResetToken::where('token_hash', $tokenHash)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->first();

        if ($eloquent === null) {
            return null;
        }

        return $this->toDomain($eloquent);
    }

    public function findByTokenHash(string $tokenHash): ?PasswordResetToken
    {
        $eloquent = EloquentPasswordResetToken::where('token_hash', $tokenHash)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($eloquent === null) {
            return null;
        }

        return $this->toDomain($eloquent);
    }

    public function delete(PasswordResetToken $token): void
    {
        EloquentPasswordResetToken::where('id', $token->id)->delete();
    }

    public function findLatestValidByEmail(string $email): ?PasswordResetToken
    {
        $eloquent = EloquentPasswordResetToken::where('email', $email)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->first();

        if ($eloquent === null) {
            return null;
        }

        return $this->toDomain($eloquent);
    }

    private function toDomain(EloquentPasswordResetToken $eloquent): PasswordResetToken
    {
        return new PasswordResetToken(
            id: (string) $eloquent->id,
            email: $eloquent->email,
            tokenHash: $eloquent->token_hash,
            expiresAt: \DateTimeImmutable::createFromMutable($eloquent->expires_at),
            createdAt: \DateTimeImmutable::createFromMutable($eloquent->created_at),
            updatedAt: \DateTimeImmutable::createFromMutable($eloquent->updated_at),
        );
    }
}
