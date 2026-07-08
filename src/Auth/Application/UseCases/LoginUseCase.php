<?php

declare(strict_types=1);

namespace Urbania\Auth\Application\UseCases;

use Urbania\Auth\Application\DTOs\LoginRequestDto;
use Urbania\Auth\Domain\Exceptions\AccountNotActiveException;
use Urbania\Auth\Domain\Exceptions\InvalidCredentialsException;
use Urbania\Auth\Domain\Repositories\UserRepositoryInterface;
use Urbania\Mfa\Domain\Repositories\UserMfaRepositoryInterface;
use Urbania\Shared\JWT\JwtService;

final readonly class LoginUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private JwtService $jwtService,
        private ?UserMfaRepositoryInterface $mfaRepository = null,
    ) {}

    /**
     * Authenticate a user and issue tokens.
     *
     * @return array{access_token?: string, refresh_token?: string, expires_in?: int, mfa_required?: bool, mfa_token?: string}
     *
     * @throws InvalidCredentialsException
     * @throws AccountNotActiveException
     */
    public function execute(LoginRequestDto $dto): array
    {
        $user = $this->userRepository->findByEmail($dto->email);

        if ($user === null) {
            throw new InvalidCredentialsException;
        }

        if (! password_verify($dto->password, $user->password_hash)) {
            throw new InvalidCredentialsException;
        }

        if ($user->estado !== 'active') {
            throw new AccountNotActiveException;
        }

        // Check if user has MFA enabled
        if ($this->mfaRepository !== null && $this->mfaRepository->existsByUserId((string) $user->id)) {
            $mfaToken = $this->jwtService->issueMfaToken((string) $user->id);

            return [
                'mfa_required' => true,
                'mfa_token' => $mfaToken,
            ];
        }

        $accessToken = $this->jwtService->issueAccessToken((string) $user->id);
        $refreshToken = $this->jwtService->issueRefreshToken((string) $user->id);

        $ttl = config('jwt.ttl');
        $expiresIn = is_int($ttl) ? $ttl : 900;

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $expiresIn,
        ];
    }
}
