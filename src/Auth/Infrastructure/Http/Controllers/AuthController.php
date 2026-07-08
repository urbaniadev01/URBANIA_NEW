<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Urbania\Auth\Application\DTOs\LoginRequestDto;
use Urbania\Auth\Application\DTOs\RegisterUserRequestDto;
use Urbania\Auth\Application\UseCases\LoginUseCase;
use Urbania\Auth\Application\UseCases\LogoutUseCase;
use Urbania\Auth\Application\UseCases\RefreshTokenUseCase;
use Urbania\Auth\Application\UseCases\RegisterUserUseCase;
use Urbania\Auth\Domain\Exceptions\AccountNotActiveException;
use Urbania\Auth\Domain\Exceptions\EmailAlreadyRegisteredException;
use Urbania\Auth\Domain\Exceptions\InvalidCredentialsException;
use Urbania\Auth\Domain\Exceptions\InvitationTokenInvalidException;
use Urbania\Auth\Domain\Exceptions\RefreshTokenExpiredException;
use Urbania\Auth\Domain\Exceptions\RefreshTokenMissingException;
use Urbania\Auth\Domain\Exceptions\RefreshTokenReusedException;
use Urbania\Auth\Infrastructure\Http\Requests\LoginRequest;
use Urbania\Auth\Infrastructure\Http\Requests\RegisterRequest;
use Urbania\Auth\Infrastructure\Http\Resources\UserResource;

final readonly class AuthController
{
    public function __construct(
        private RegisterUserUseCase $registerUser,
        private LoginUseCase $login,
        private RefreshTokenUseCase $refreshToken,
        private LogoutUseCase $logout,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $dto = new RegisterUserRequestDto(
            invitationToken: (string) $validated['invitation_token'],
            password: (string) $validated['password'],
            name: (string) $validated['name'],
            phone: isset($validated['phone']) ? (string) $validated['phone'] : null,
        );

        try {
            $result = $this->registerUser->execute($dto);
        } catch (InvitationTokenInvalidException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INVITATION_TOKEN_INVALID',
                    'message' => $e->getMessage(),
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 403);
        } catch (EmailAlreadyRegisteredException $e) {
            return response()->json([
                'error' => [
                    'code' => 'EMAIL_ALREADY_REGISTERED',
                    'message' => $e->getMessage(),
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 409);
        }

        $resource = new UserResource($result['user']);
        $resource->contactName = $result['contact_name'];

        return $resource
            ->additional(['message' => 'Registro exitoso'])
            ->response()
            ->setStatusCode(201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $dto = new LoginRequestDto(
            email: (string) $validated['email'],
            password: (string) $validated['password'],
        );

        try {
            $result = $this->login->execute($dto);
        } catch (InvalidCredentialsException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'message' => $e->getMessage(),
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 401);
        } catch (AccountNotActiveException $e) {
            return response()->json([
                'error' => [
                    'code' => 'ACCOUNT_NOT_ACTIVE',
                    'message' => $e->getMessage(),
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 403);
        }

        // MFA required — return mfa_token instead of access_token
        if (isset($result['mfa_required']) && $result['mfa_required'] === true) {
            return response()->json([
                'mfa_required' => true,
                'mfa_token' => $result['mfa_token'],
            ])
                ->withCookie(cookie(
                    'mfa_token',
                    $result['mfa_token'],
                    5, // 5 minutes
                    '/api/v1/auth',
                    null,
                    true,
                    true,
                    false,
                    'Strict',
                ));
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
                true,
                true,
                false,
                'Strict',
            ));
    }

    public function refresh(): JsonResponse
    {
        $refreshTokenCookie = request()->cookie('refresh_token');

        if (is_array($refreshTokenCookie)) {
            $refreshTokenCookie = null;
        }

        try {
            $result = $this->refreshToken->execute(
                is_string($refreshTokenCookie) ? $refreshTokenCookie : null,
            );
        } catch (RefreshTokenMissingException $e) {
            return response()->json([
                'error' => [
                    'code' => 'REFRESH_TOKEN_MISSING',
                    'message' => $e->getMessage(),
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 401);
        } catch (RefreshTokenExpiredException $e) {
            return response()->json([
                'error' => [
                    'code' => 'REFRESH_TOKEN_EXPIRED',
                    'message' => $e->getMessage(),
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 401);
        } catch (RefreshTokenReusedException $e) {
            return response()->json([
                'error' => [
                    'code' => 'REFRESH_TOKEN_REUSED',
                    'message' => $e->getMessage(),
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 401);
        } catch (AccountNotActiveException $e) {
            return response()->json([
                'error' => [
                    'code' => 'ACCOUNT_NOT_ACTIVE',
                    'message' => $e->getMessage(),
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 403);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'An unexpected error occurred',
                    'trace_id' => (string) Str::orderedUuid(),
                ],
            ], 500);
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
                true,
                true,
                false,
                'Strict',
            ));
    }

    public function logout(): JsonResponse
    {
        $refreshTokenCookie = request()->cookie('refresh_token');

        if (is_array($refreshTokenCookie)) {
            $refreshTokenCookie = null;
        }

        $this->logout->execute(
            is_string($refreshTokenCookie) ? $refreshTokenCookie : null,
        );

        return response()->json([
            'message' => 'Sesión cerrada exitosamente.',
        ])
            ->withCookie(cookie(
                'refresh_token',
                '',
                -1,
                '/api/v1/auth',
                null,
                true,
                true,
                false,
                'Strict',
            ));
    }
}
