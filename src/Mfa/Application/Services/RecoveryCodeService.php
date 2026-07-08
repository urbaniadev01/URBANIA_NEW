<?php

declare(strict_types=1);

namespace Urbania\Mfa\Application\Services;

final readonly class RecoveryCodeService
{
    private const CODE_COUNT = 8;

    private const CODE_LENGTH = 10;

    private const CODE_CHUNK_SIZE = 5;

    /**
     * Generate a set of plaintext recovery codes.
     *
     * @return array{plain: array<int, string>, hashed: array<int, array{hash: string, used_at: null}>}
     */
    public function generate(): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < self::CODE_COUNT; $i++) {
            $code = $this->generateCode();
            $plain[] = $code;
            $hashed[] = [
                'hash' => password_hash($code, PASSWORD_BCRYPT, ['cost' => 12]),
                'used_at' => null,
            ];
        }

        return [
            'plain' => $plain,
            'hashed' => $hashed,
        ];
    }

    /**
     * Verify a plaintext code against a set of hashed recovery codes.
     * Returns the index of the matching code, or null if not found / already used.
     *
     * @param array<int, array{hash: string, used_at: string|null}> $hashedCodes
     */
    public function verify(string $plainCode, array $hashedCodes): ?int
    {
        foreach ($hashedCodes as $index => $code) {
            if ($code['used_at'] !== null) {
                continue;
            }

            if (password_verify($plainCode, $code['hash'])) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Check if a recovery code has already been used.
     *
     * @param array<int, array{hash: string, used_at: string|null}> $hashedCodes
     */
    public function isUsed(string $plainCode, array $hashedCodes): bool
    {
        foreach ($hashedCodes as $code) {
            if (password_verify($plainCode, $code['hash'])) {
                return $code['used_at'] !== null;
            }
        }

        return false;
    }

    private function generateCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        // Insert dashes for readability: XXXXX-XXXXX
        return substr($code, 0, self::CODE_CHUNK_SIZE).'-'.substr($code, self::CODE_CHUNK_SIZE);
    }
}
