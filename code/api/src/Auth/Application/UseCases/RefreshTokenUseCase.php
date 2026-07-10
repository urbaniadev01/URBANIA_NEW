<?php

declare(strict_types=1);

namespace Urbania\Auth\Application\UseCases;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Urbania\Auth\Domain\Exceptions\AccountNotActiveException;
use Urbania\Auth\Domain\Exceptions\RefreshTokenExpiredException;
use Urbania\Auth\Domain\Exceptions\RefreshTokenMissingException;
use Urbania\Auth\Domain\Exceptions\RefreshTokenReusedException;
use Urbania\Auth\Domain\Repositories\RefreshTokenRepositoryInterface;
use Urbania\Auth\Domain\Repositories\UserRepositoryInterface;
use Urbania\Mfa\Domain\Repositories\UserMfaRepositoryInterface;
use Urbania\Shared\JWT\JwtService;

final readonly class RefreshTokenUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private JwtService $jwtService,
        private ?UserMfaRepositoryInterface $mfaRepository = null,
    ) {}

    /**
     * Refresh an access token using a refresh token from an httpOnly cookie.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     *
     * @throws RefreshTokenMissingException
     * @throws RefreshTokenExpiredException
     * @throws RefreshTokenReusedException
     * @throws AccountNotActiveException
     */
    public function execute(?string $refreshTokenCookie): array
    {
        // 1. Check cookie exists
        if ($refreshTokenCookie === null || $refreshTokenCookie === '') {
            throw new RefreshTokenMissingException;
        }

        // 2. Verify JWT and extract payload
        try {
            $payload = $this->jwtService->verify($refreshTokenCookie);
        } catch (ExpiredException $e) {
            throw new RefreshTokenExpiredException;
        } catch (SignatureInvalidException|\UnexpectedValueException $e) {
            throw new RefreshTokenMissingException;
        }

        // 3. Validate token type is refresh
        if (($payload->type ?? '') !== 'refresh') {
            throw new RefreshTokenMissingException;
        }

        // 4. Extract claims
        $userId = $payload->sub;
        $jti = $payload->jti;

        // 5. Verify user exists and is active
        $user = $this->userRepository->findById($userId);

        if ($user === null || $user->estado !== 'active') {
            throw new AccountNotActiveException;
        }

        // 6. Check refresh token state in database
        $existingToken = $this->refreshTokenRepository->findByJti($jti);

        if ($existingToken === null) {
            // First use: create record as valido
            $this->refreshTokenRepository->create([
                'user_id' => $userId,
                'jti' => $jti,
                'estado' => 'valido',
                'expires_at' => date('c', $payload->exp),
            ]);
        } elseif ($existingToken->estado === 'valido') {
            // Normal rotation: mark as invalidado
            $this->refreshTokenRepository->invalidateByJti($jti);
        } else {
            // Reuse detected: mass revocation of all user's refresh tokens
            $this->refreshTokenRepository->invalidateAllByUserId($userId);

            throw new RefreshTokenReusedException;
        }

        // 7. Issue new token pair
        $claims = [];

        if ($this->mfaRepository?->existsByUserId($userId)) {
            $claims['mfa_verified'] = true;
        }

        $accessToken = $this->jwtService->issueAccessToken($userId, $claims);
        $refreshToken = $this->jwtService->issueRefreshToken($userId);

        // 8. Persist the new refresh token so rotation tracking works end-to-end
        $newPayload = $this->jwtService->verify($refreshToken);

        $this->refreshTokenRepository->create([
            'user_id' => $userId,
            'jti' => $newPayload->jti,
            'estado' => 'valido',
            'expires_at' => date('c', $newPayload->exp),
        ]);

        $ttl = config('jwt.ttl');
        $expiresIn = is_int($ttl) ? $ttl : 900;

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $expiresIn,
        ];
    }
}
