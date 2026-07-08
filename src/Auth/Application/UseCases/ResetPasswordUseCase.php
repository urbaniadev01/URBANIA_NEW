<?php

declare(strict_types=1);

namespace Urbania\Auth\Application\UseCases;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Urbania\Auth\Application\DTOs\ResetPasswordRequestDto;
use Urbania\Auth\Domain\Exceptions\PasswordResetRateLimitException;
use Urbania\Auth\Domain\Exceptions\ResetTokenExpiredException;
use Urbania\Auth\Domain\Exceptions\ResetTokenInvalidException;
use Urbania\Auth\Domain\Repositories\PasswordResetTokenRepositoryInterface;
use Urbania\Auth\Domain\Repositories\UserRepositoryInterface;

final readonly class ResetPasswordUseCase
{
    private const RATE_LIMIT_MAX = 5;
    private const RATE_LIMIT_WINDOW = 900; // 15 minutes

    public function __construct(
        private PasswordResetTokenRepositoryInterface $passwordResetTokenRepository,
        private UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Reset a user's password using a valid reset token.
     *
     * @throws ResetTokenInvalidException
     * @throws ResetTokenExpiredException
     * @throws PasswordResetRateLimitException
     */
    public function execute(ResetPasswordRequestDto $dto, string $ip): string
    {
        $this->checkRateLimit($ip);

        $tokenHash = hash('sha256', $dto->token);

        // First, check if the token exists at all (including expired ones)
        // to distinguish between "invalid" and "expired"
        $anyToken = $this->passwordResetTokenRepository->findByTokenHash($tokenHash);

        if ($anyToken === null) {
            // Token never existed or was already deleted (used)
            throw new ResetTokenInvalidException;
        }

        // Check if token is expired
        $now = new \DateTimeImmutable;
        if ($anyToken->expiresAt < $now) {
            // Clean up expired token
            $this->passwordResetTokenRepository->delete($anyToken);

            throw new ResetTokenExpiredException;
        }

        // Token is valid and not expired
        $resetToken = $anyToken;

        // Find user by email from the token
        $user = $this->userRepository->findByEmail($resetToken->email);

        if ($user === null) {
            // User deleted between token creation and reset attempt
            throw new ResetTokenInvalidException;
        }

        // Update password
        $user->update(['password_hash' => Hash::make($dto->password)]);

        // Delete the token — one-time use
        $this->passwordResetTokenRepository->delete($resetToken);

        // Also clean up the dev Redis key
        Redis::del('dev:password_reset:plain:'.$resetToken->email);

        return 'Contraseña actualizada exitosamente.';
    }

    private function checkRateLimit(string $ip): void
    {
        $rateKey = 'password_reset_attempts:'.$ip;

        $current = Redis::incr($rateKey);

        if ($current === 1) {
            Redis::expire($rateKey, self::RATE_LIMIT_WINDOW);
        }

        if ($current > self::RATE_LIMIT_MAX) {
            throw new PasswordResetRateLimitException;
        }
    }
}
