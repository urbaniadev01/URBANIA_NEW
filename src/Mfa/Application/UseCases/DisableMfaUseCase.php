<?php

declare(strict_types=1);

namespace Urbania\Mfa\Application\UseCases;

use App\Models\User;
use Urbania\Mfa\Application\Services\TotpService;
use Urbania\Mfa\Domain\Exceptions\MfaCodeInvalidException;
use Urbania\Mfa\Domain\Exceptions\MfaNotEnabledException;
use Urbania\Mfa\Domain\Repositories\UserMfaRepositoryInterface;

final readonly class DisableMfaUseCase
{
    public function __construct(
        private UserMfaRepositoryInterface $mfaRepository,
        private TotpService $totpService,
    ) {}

    /**
     * Disable MFA for a user (requires valid TOTP code).
     *
     * @return array{message: string}
     *
     * @throws MfaNotEnabledException
     * @throws MfaCodeInvalidException
     */
    public function execute(User $user, string $code): array
    {
        $mfa = $this->mfaRepository->findByUserId((string) $user->id);

        if ($mfa === null) {
            throw new MfaNotEnabledException;
        }

        if (! $this->totpService->verify($mfa->totpSecret, $code)) {
            throw new MfaCodeInvalidException;
        }

        $this->mfaRepository->deleteByUserId((string) $user->id);

        return [
            'message' => 'MFA desactivado exitosamente.',
        ];
    }
}
