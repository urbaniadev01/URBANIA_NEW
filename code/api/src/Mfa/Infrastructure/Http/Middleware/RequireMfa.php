<?php

declare(strict_types=1);

namespace Urbania\Mfa\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Urbania\Shared\JWT\JwtService;

final class RequireMfa
{
    public function __construct(
        private readonly JwtService $jwtService,
    ) {}

    /**
     * Reject requests that do not have a fully-verified MFA access token.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return $this->mfaRequiredResponse();
        }

        try {
            $payload = $this->jwtService->verify($token);
        } catch (\Throwable) {
            return $this->mfaRequiredResponse();
        }

        // Check if this is an mfa_token (type 'mfa') — should never be used for protected endpoints
        if (isset($payload->type) && $payload->type === 'mfa') {
            return $this->mfaRequiredResponse();
        }

        // Check mfa_verified claim
        if (! isset($payload->mfa_verified) || $payload->mfa_verified !== true) {
            return $this->mfaRequiredResponse();
        }

        return $next($request);
    }

    private function mfaRequiredResponse(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'MFA_REQUIRED',
                'message' => 'Se requiere verificación MFA para acceder a este recurso.',
                'trace_id' => request()->header('X-Trace-Id', (string) Str::orderedUuid()),
            ],
        ], 403);
    }
}
