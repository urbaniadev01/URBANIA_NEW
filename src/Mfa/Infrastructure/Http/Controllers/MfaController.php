<?php

declare(strict_types=1);

namespace Urbania\Mfa\Infrastructure\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Urbania\Mfa\Application\UseCases\ConfirmMfaUseCase;
use Urbania\Mfa\Application\UseCases\DisableMfaUseCase;
use Urbania\Mfa\Application\UseCases\EnrollMfaUseCase;
use Urbania\Mfa\Application\UseCases\RegenerateRecoveryCodesUseCase;
use Urbania\Mfa\Application\UseCases\VerifyMfaUseCase;
use Urbania\Mfa\Infrastructure\Http\Requests\ConfirmMfaRequest;
use Urbania\Mfa\Infrastructure\Http\Requests\DisableMfaRequest;
use Urbania\Mfa\Infrastructure\Http\Requests\EnrollMfaRequest;
use Urbania\Mfa\Infrastructure\Http\Requests\RegenerateRecoveryCodesRequest;
use Urbania\Mfa\Infrastructure\Http\Requests\VerifyMfaRequest;
use Urbania\Shared\Domain\DomainException;
use Urbania\Shared\JWT\JwtService;

final readonly class MfaController
{
    public function __construct(
        private EnrollMfaUseCase $enrollMfa,
        private ConfirmMfaUseCase $confirmMfa,
        private VerifyMfaUseCase $verifyMfa,
        private DisableMfaUseCase $disableMfa,
        private RegenerateRecoveryCodesUseCase $regenerateRecoveryCodes,
        private JwtService $jwtService,
    ) {}

    public function enroll(EnrollMfaRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $this->errorResponse('UNAUTHENTICATED', 'No autenticado.', 401);
        }

        try {
            $result = $this->enrollMfa->execute($user);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getHttpStatusCode());
        }

        return response()->json($result, 201);
    }

    public function confirm(ConfirmMfaRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $this->errorResponse('UNAUTHENTICATED', 'No autenticado.', 401);
        }

        $validated = $request->validated();

        try {
            $result = $this->confirmMfa->execute($user, (string) $validated['code']);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getHttpStatusCode());
        }

        return response()->json($result, 200);
    }

    public function verify(VerifyMfaRequest $request): JsonResponse
    {
        // The user is resolved from mfa_token (which has sub claim)
        $user = $this->resolveUserFromMfaToken($request);

        if ($user === null) {
            return $this->errorResponse('MFA_TOKEN_INVALID', 'El token MFA no es válido o ha expirado.', 401);
        }

        $validated = $request->validated();

        try {
            $result = $this->verifyMfa->execute($user, (string) $validated['code']);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getHttpStatusCode());
        }

        $refreshTtl = config('jwt.refresh_ttl');
        $refreshMinutes = (is_int($refreshTtl) ? $refreshTtl : 1209600) / 60;

        return response()->json([
            'access_token' => $result['access_token'],
            'token_type' => 'Bearer',
            'expires_in' => $result['expires_in'],
        ])
            ->withCookie(cookie(
                'refresh_token',
                $result['refresh_token'],
                (int) $refreshMinutes,
                '/api/v1/auth',
                null,
                config('session.secure'),
                true,
                false,
                'Strict',
            ));
    }

    public function disable(DisableMfaRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $this->errorResponse('UNAUTHENTICATED', 'No autenticado.', 401);
        }

        $validated = $request->validated();

        try {
            $result = $this->disableMfa->execute($user, (string) $validated['code']);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getHttpStatusCode());
        }

        return response()->json($result, 200);
    }

    public function recovery(RegenerateRecoveryCodesRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $this->errorResponse('UNAUTHENTICATED', 'No autenticado.', 401);
        }

        $validated = $request->validated();

        try {
            $result = $this->regenerateRecoveryCodes->execute($user, (string) $validated['code']);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getHttpStatusCode());
        }

        return response()->json($result, 200);
    }

    private function resolveUserFromMfaToken(Request $request): ?User
    {
        $token = $request->cookie('mfa_token');

        if (! is_string($token) || $token === '') {
            // Also check Authorization header for API testing convenience
            $token = $request->bearerToken();
        }

        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $payload = $this->jwtService->verify($token);

            // Only mfa tokens
            if (! isset($payload->type) || $payload->type !== 'mfa') {
                return null;
            }

            if (! isset($payload->sub) || ! is_string($payload->sub)) {
                return null;
            }

            return User::find($payload->sub);
        } catch (\Throwable) {
            return null;
        }
    }

    private function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'trace_id' => request()->header('X-Trace-Id', (string) Str::orderedUuid()),
            ],
        ], $status);
    }
}
