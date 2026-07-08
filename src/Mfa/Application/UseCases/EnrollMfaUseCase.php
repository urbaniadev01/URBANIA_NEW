<?php

declare(strict_types=1);

namespace Urbania\Mfa\Application\UseCases;

use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Urbania\Mfa\Application\Services\RecoveryCodeService;
use Urbania\Mfa\Application\Services\TotpService;
use Urbania\Mfa\Domain\Exceptions\MfaAlreadyEnabledException;
use Urbania\Mfa\Domain\Exceptions\MfaRateLimitException;
use Urbania\Mfa\Domain\Repositories\UserMfaRepositoryInterface;

final readonly class EnrollMfaUseCase
{
    private const ENROLLMENT_TTL = 600;
    private const ENROLLMENT_RATE_LIMIT = 3;
    private const ENROLLMENT_RATE_WINDOW = 3600;

    public function __construct(
        private UserMfaRepositoryInterface $mfaRepository,
        private TotpService $totpService,
        private RecoveryCodeService $recoveryCodeService,
    ) {}

    /**
     * Initiate MFA enrollment for a user.
     *
     * @return array{qr_code: string, recovery_codes: array<int, string>, enrollment_token: string}
     *
     * @throws MfaAlreadyEnabledException
     * @throws MfaRateLimitException
     */
    public function execute(User $user): array
    {
        if ($this->mfaRepository->existsByUserId((string) $user->id)) {
            throw new MfaAlreadyEnabledException;
        }

        $this->checkRateLimit((string) $user->id);

        $secret = $this->totpService->generateSecret();
        $qrCode = $this->totpService->generateQrCodeBase64($secret, $user->email);

        $codes = $this->recoveryCodeService->generate();

        // Store pending enrollment in Redis
        $key = $this->enrollmentKey((string) $user->id);
        $enrollmentData = json_encode([
            'secret' => $secret,
            'recovery_codes' => $codes['hashed'],
            'attempts' => 0,
        ]);

        Redis::setex($key, self::ENROLLMENT_TTL, $enrollmentData);

        // Generate a simple enrollment token
        $enrollmentToken = bin2hex(random_bytes(32));

        return [
            'qr_code' => $qrCode,
            'recovery_codes' => $codes['plain'],
            'enrollment_token' => $enrollmentToken,
        ];
    }

    private function checkRateLimit(string $userId): void
    {
        $rateKey = 'mfa_enroll_rate:'.$userId;
        $maxAttempts = self::ENROLLMENT_RATE_LIMIT;
        $window = self::ENROLLMENT_RATE_WINDOW;

        $current = Redis::incr($rateKey);

        if ($current === 1) {
            Redis::expire($rateKey, $window);
        }

        if ($current > $maxAttempts) {
            throw new MfaRateLimitException('Demasiados intentos. Intente nuevamente más tarde.');
        }
    }

    private function enrollmentKey(string $userId): string
    {
        return 'mfa_enrollment:'.$userId;
    }
}
