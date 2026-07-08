<?php

declare(strict_types=1);

namespace Urbania\Auth\Application\UseCases;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Urbania\Auth\Domain\Repositories\RefreshTokenRepositoryInterface;
use Urbania\Shared\JWT\JwtService;

final readonly class LogoutUseCase
{
    public function __construct(
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private JwtService $jwtService,
    ) {}

    /**
     * Revoke the current refresh token and terminate the session server-side.
     *
     * Idempotent: always returns without error regardless of whether the
     * token is present, valid, expired, or already revoked.
     */
    public function execute(?string $refreshTokenCookie): void
    {
        // 1. No cookie → nothing to revoke (idempotent, not an error)
        if ($refreshTokenCookie === null || $refreshTokenCookie === '') {
            return;
        }

        // 2. Try to decode the JWT to extract the jti
        try {
            $payload = $this->jwtService->verify($refreshTokenCookie);
        } catch (ExpiredException|SignatureInvalidException|\UnexpectedValueException) {
            // Token is already invalid (expired, tampered, malformed) —
            // the session is de facto terminated. Idempotent: return 200.
            return;
        }

        // 3. Validate token type is refresh
        if (($payload->type ?? '') !== 'refresh') {
            // Not a refresh token — nothing to revoke. Idempotent.
            return;
        }

        // 4. Invalidate by jti (no-op if it doesn't exist in DB)
        $this->refreshTokenRepository->invalidateByJti($payload->jti);
    }
}
