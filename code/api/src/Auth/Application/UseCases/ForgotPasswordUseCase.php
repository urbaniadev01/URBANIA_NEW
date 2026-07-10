<?php

declare(strict_types=1);

namespace Urbania\Auth\Application\UseCases;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Urbania\Auth\Application\DTOs\ForgotPasswordRequestDto;
use Urbania\Auth\Domain\Exceptions\PasswordResetRateLimitException;
use Urbania\Auth\Domain\Repositories\PasswordResetTokenRepositoryInterface;
use Urbania\Auth\Domain\Repositories\UserRepositoryInterface;
use Urbania\Auth\Infrastructure\Mail\ResetPasswordMail;

final readonly class ForgotPasswordUseCase
{
    private const TOKEN_LENGTH = 32; // bytes → 64 hex chars
    private const TOKEN_TTL_MINUTES = 60;
    private const RATE_LIMIT_MAX = 3;
    private const RATE_LIMIT_WINDOW = 3600; // 1 hour

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordResetTokenRepositoryInterface $passwordResetTokenRepository,
    ) {}

    /**
     * Process a forgot-password request.
     *
     * Always returns the same generic message — never reveals whether the email exists.
     *
     * @throws PasswordResetRateLimitException
     */
    public function execute(ForgotPasswordRequestDto $dto): string
    {
        $this->checkRateLimit($dto->email);

        if (! $this->userRepository->existsByEmail($dto->email)) {
            // Simulate processing time to prevent timing attack
            usleep(100000); // 100ms

            return 'Si el email está registrado, recibirás un enlace de recuperación.';
        }

        $plainToken = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $expiresAt = new \DateTimeImmutable('+'.self::TOKEN_TTL_MINUTES.' minutes');

        $this->passwordResetTokenRepository->create($dto->email, $plainToken, $expiresAt);

        if (app()->environment('local', 'testing')) {
            $devKey = 'dev:password_reset:plain:'.$dto->email;
            Redis::setex($devKey, self::TOKEN_TTL_MINUTES * 60, $plainToken);
        }

        $webUrl = (string) config('app.web_url', 'http://localhost:3000');
        Mail::to($dto->email)->send(new ResetPasswordMail($plainToken, $dto->email, $webUrl));

        return 'Si el email está registrado, recibirás un enlace de recuperación.';
    }

    private function checkRateLimit(string $email): void
    {
        $rateKey = 'password_reset_forgot:'.$email;

        $current = Redis::incr($rateKey);

        if ($current === 1) {
            Redis::expire($rateKey, self::RATE_LIMIT_WINDOW);
        }

        if ($current > self::RATE_LIMIT_MAX) {
            throw new PasswordResetRateLimitException;
        }
    }
}
