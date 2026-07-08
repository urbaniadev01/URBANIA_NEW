<?php

declare(strict_types=1);

namespace Urbania\Mfa\Application\UseCases;

use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Urbania\Mfa\Application\Services\TotpService;
use Urbania\Mfa\Domain\Exceptions\MfaCodeInvalidException;
use Urbania\Mfa\Domain\Exceptions\MfaEnrollmentExpiredException;
use Urbania\Mfa\Domain\Exceptions\MfaEnrollmentNotFoundException;
use Urbania\Mfa\Domain\Repositories\UserMfaRepositoryInterface;

final readonly class ConfirmMfaUseCase
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private UserMfaRepositoryInterface $mfaRepository,
        private TotpService $totpService,
    ) {}

    /**
     * Confirm MFA enrollment with a TOTP code.
     *
     * @return array{message: string}
     *
     * @throws MfaEnrollmentNotFoundException
     * @throws MfaCodeInvalidException
     * @throws MfaEnrollmentExpiredException
     */
    public function execute(User $user, string $code): array
    {
        $key = $this->enrollmentKey((string) $user->id);
        $raw = Redis::get($key);

        if (! is_string($raw)) {
            throw new MfaEnrollmentNotFoundException;
        }

        /** @var array{secret: string, recovery_codes: array<int, array{hash: string, used_at: null}>, attempts: int}|null $data */
        $data = json_decode($raw, true);

        if ($data === null) {
            throw new MfaEnrollmentNotFoundException;
        }

        $secret = (string) $data['secret'];
        $recoveryCodes = $data['recovery_codes'];
        $attempts = (int) $data['attempts'];

        if (! $this->totpService->verify($secret, $code)) {
            $attempts++;
            Redis::setex($key, 600, json_encode([
                'secret' => $secret,
                'recovery_codes' => $recoveryCodes,
                'attempts' => $attempts,
            ]));

            if ($attempts >= self::MAX_ATTEMPTS) {
                Redis::del($key);

                throw new MfaEnrollmentExpiredException;
            }

            throw new MfaCodeInvalidException;
        }

        // Enrollment confirmed — persist
        $enabledAt = new \DateTimeImmutable;

        $this->mfaRepository->create([
            'id' => (string) Str::orderedUuid(),
            'user_id' => (string) $user->id,
            'totp_secret' => $secret,
            'recovery_codes' => $recoveryCodes,
            'enabled_at' => $enabledAt->format('Y-m-d\TH:i:s.v\Z'),
        ]);

        // Clean up Redis
        Redis::del($key);

        return [
            'message' => 'MFA activado exitosamente.',
        ];
    }

    private function enrollmentKey(string $userId): string
    {
        return 'mfa_enrollment:'.$userId;
    }
}
