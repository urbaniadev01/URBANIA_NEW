<?php

declare(strict_types=1);

namespace Urbania\Mfa\Domain\Models;

final readonly class UserMfa
{
    /**
     * @param array<int, array{hash: string, used_at: string|null}> $recoveryCodes
     */
    public function __construct(
        public string $id,
        public string $userId,
        public string $totpSecret,
        public array $recoveryCodes,
        public \DateTimeImmutable $enabledAt,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {}

    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * @return array<int, array{hash: string, used_at: string|null}>
     */
    public function unusedRecoveryCodes(): array
    {
        return array_values(array_filter(
            $this->recoveryCodes,
            static fn (array $code): bool => $code['used_at'] === null,
        ));
    }

    /**
     * @return array<int, array{hash: string, used_at: string|null}>
     */
    public function markRecoveryCodeUsed(string $plainCode): array
    {
        $updated = [];

        foreach ($this->recoveryCodes as $code) {
            if ($code['used_at'] === null && password_verify($plainCode, $code['hash'])) {
                $updated[] = [
                    'hash' => $code['hash'],
                    'used_at' => (new \DateTimeImmutable)->format('Y-m-d\TH:i:s.v\Z'),
                ];
            } else {
                $updated[] = $code;
            }
        }

        return $updated;
    }

    public function hasUnusedRecoveryCodes(): bool
    {
        return count($this->unusedRecoveryCodes()) > 0;
    }
}
