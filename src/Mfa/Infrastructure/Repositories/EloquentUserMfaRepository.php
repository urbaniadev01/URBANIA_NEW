<?php

declare(strict_types=1);

namespace Urbania\Mfa\Infrastructure\Repositories;

use Illuminate\Support\Facades\Crypt;
use Urbania\Mfa\Domain\Models\UserMfa;
use Urbania\Mfa\Domain\Repositories\UserMfaRepositoryInterface;
use Urbania\Mfa\Infrastructure\Models\EloquentUserMfa;

final readonly class EloquentUserMfaRepository implements UserMfaRepositoryInterface
{
    public function findByUserId(string $userId): ?UserMfa
    {
        $row = EloquentUserMfa::where('user_id', $userId)->first();

        if ($row === null) {
            return null;
        }

        return $this->toDomain($row);
    }

    public function existsByUserId(string $userId): bool
    {
        return EloquentUserMfa::where('user_id', $userId)->exists();
    }

    /**
     * @param array{id: string, user_id: string, totp_secret: string, recovery_codes: string, enabled_at: string} $data
     */
    public function create(array $data): UserMfa
    {
        $data['totp_secret'] = Crypt::encrypt($data['totp_secret']);

        $row = new EloquentUserMfa($data);
        $row->save();

        return $this->toDomain($row);
    }

    /**
     * @param array<int, array{hash: string, used_at: string|null}> $recoveryCodes
     */
    public function updateRecoveryCodes(string $userId, array $recoveryCodes): void
    {
        EloquentUserMfa::where('user_id', $userId)
            ->update([
                'recovery_codes' => json_encode($recoveryCodes),
                'updated_at' => now(),
            ]);
    }

    public function deleteByUserId(string $userId): void
    {
        EloquentUserMfa::where('user_id', $userId)->delete();
    }

    private function toDomain(EloquentUserMfa $row): UserMfa
    {
        $secret = Crypt::decrypt($row->totp_secret);

        /** @var array<int, array{hash: string, used_at: string|null}> $recoveryCodes */
        $recoveryCodes = $row->recovery_codes;

        return new UserMfa(
            id: (string) $row->id,
            userId: (string) $row->user_id,
            totpSecret: $secret,
            recoveryCodes: $recoveryCodes,
            enabledAt: $row->enabled_at
                ? \DateTimeImmutable::createFromMutable($row->enabled_at)
                : new \DateTimeImmutable('now'),
            createdAt: $row->created_at
                ? \DateTimeImmutable::createFromMutable($row->created_at)
                : new \DateTimeImmutable('now'),
            updatedAt: $row->updated_at
                ? \DateTimeImmutable::createFromMutable($row->updated_at)
                : new \DateTimeImmutable('now'),
        );
    }
}
