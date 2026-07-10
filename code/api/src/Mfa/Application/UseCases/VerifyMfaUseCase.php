<?php

declare(strict_types=1);

namespace Urbania\Mfa\Application\UseCases;

use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Urbania\Mfa\Application\Services\RecoveryCodeService;
use Urbania\Mfa\Application\Services\TotpService;
use Urbania\Mfa\Domain\Exceptions\MfaCodeInvalidException;
use Urbania\Mfa\Domain\Exceptions\MfaNotEnabledException;
use Urbania\Mfa\Domain\Exceptions\MfaRateLimitException;
use Urbania\Mfa\Domain\Exceptions\MfaRecoveryCodeUsedException;
use Urbania\Mfa\Domain\Repositories\UserMfaRepositoryInterface;
use Urbania\Shared\JWT\JwtService;

final readonly class VerifyMfaUseCase
{
    private const MAX_ATTEMPTS_PER_MINUTE = 5;

    public function __construct(
        private UserMfaRepositoryInterface $mfaRepository,
        private TotpService $totpService,
        private RecoveryCodeService $recoveryCodeService,
        private JwtService $jwtService,
    ) {}

    /**
     * Verify MFA code during login flow.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     *
     * @throws MfaNotEnabledException
     * @throws MfaCodeInvalidException
     * @throws MfaRecoveryCodeUsedException
     * @throws MfaRateLimitException
     */
    public function execute(User $user, string $code): array
    {
        $this->checkRateLimit((string) $user->id);

        $mfa = $this->mfaRepository->findByUserId((string) $user->id);

        if ($mfa === null) {
            throw new MfaNotEnabledException;
        }

        // Try TOTP first
        $totpVerified = $this->totpService->verify($mfa->totpSecret, $code);

        if ($totpVerified) {
            return $this->issueTokens($user);
        }

        // Try recovery code
        $recoveryCodes = $mfa->recoveryCodes;

        // Check if code is a previously-used recovery code
        if ($this->recoveryCodeService->isUsed($code, $recoveryCodes)) {
            throw new MfaRecoveryCodeUsedException;
        }

        $matchedIndex = $this->recoveryCodeService->verify($code, $recoveryCodes);

        if ($matchedIndex !== null) {
            // Mark code as used
            $recoveryCodes[$matchedIndex]['used_at'] = (new \DateTimeImmutable)->format('Y-m-d\TH:i:s.v\Z');
            $this->mfaRepository->updateRecoveryCodes((string) $user->id, $recoveryCodes);

            return $this->issueTokens($user);
        }

        throw new MfaCodeInvalidException;
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    private function issueTokens(User $user): array
    {
        $accessToken = $this->jwtService->issueAccessToken((string) $user->id, [
            'mfa_verified' => true,
        ]);

        $refreshToken = $this->jwtService->issueRefreshToken((string) $user->id);

        $ttl = config('jwt.ttl');
        $expiresIn = is_int($ttl) ? $ttl : 900;

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $expiresIn,
        ];
    }

    private function checkRateLimit(string $userId): void
    {
        $rateKey = 'mfa_verify_rate:'.$userId;
        $maxAttempts = self::MAX_ATTEMPTS_PER_MINUTE;
        $window = 60;

        $current = Redis::incr($rateKey);

        if ($current === 1) {
            Redis::expire($rateKey, $window);
        }

        if ($current > $maxAttempts) {
            throw new MfaRateLimitException('Demasiados intentos. Intente nuevamente en un minuto.');
        }
    }
}
