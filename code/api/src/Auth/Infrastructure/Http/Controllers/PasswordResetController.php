<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Urbania\Auth\Application\DTOs\ForgotPasswordRequestDto;
use Urbania\Auth\Application\DTOs\ResetPasswordRequestDto;
use Urbania\Auth\Application\UseCases\ForgotPasswordUseCase;
use Urbania\Auth\Application\UseCases\ResetPasswordUseCase;
use Urbania\Auth\Domain\Exceptions\PasswordResetRateLimitException;
use Urbania\Auth\Domain\Exceptions\ResetTokenExpiredException;
use Urbania\Auth\Domain\Exceptions\ResetTokenInvalidException;
use Urbania\Auth\Infrastructure\Http\Requests\ForgotPasswordRequest;
use Urbania\Auth\Infrastructure\Http\Requests\ResetPasswordRequest;

final readonly class PasswordResetController
{
    public function __construct(
        private ForgotPasswordUseCase $forgotPassword,
        private ResetPasswordUseCase $resetPassword,
    ) {}

    /**
     * POST /auth/forgot-password
     *
     * Always returns 200 with a generic message to prevent email enumeration.
     * Validation errors (invalid email format) also return 200 — handled by
     * ForgotPasswordRequest::failedValidation.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $dto = new ForgotPasswordRequestDto(
            email: (string) $validated['email'],
        );

        try {
            $message = $this->forgotPassword->execute($dto);
        } catch (PasswordResetRateLimitException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getHttpStatusCode());
        }

        return response()->json(['message' => $message]);
    }

    /**
     * POST /auth/reset-password
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $dto = new ResetPasswordRequestDto(
            token: (string) $validated['token'],
            password: (string) $validated['password'],
            passwordConfirmation: (string) $validated['password_confirmation'],
        );

        $ip = (string) $request->ip();

        try {
            $message = $this->resetPassword->execute($dto, $ip);
        } catch (ResetTokenInvalidException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getHttpStatusCode());
        } catch (ResetTokenExpiredException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getHttpStatusCode());
        } catch (PasswordResetRateLimitException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getHttpStatusCode());
        }

        return response()->json(['message' => $message]);
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
