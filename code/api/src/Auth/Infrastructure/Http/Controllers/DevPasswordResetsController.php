<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

final readonly class DevPasswordResetsController
{
    public function last(Request $request): JsonResponse
    {
        /** @var string $email */
        $email = (string) $request->query('email');

        if ($email === '') {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'El parámetro email es obligatorio.',
                    'trace_id' => request()->header('X-Trace-Id', (string) Str::orderedUuid()),
                ],
            ], 422);
        }

        $devKey = 'dev:password_reset:plain:'.$email;
        $plainToken = (string) Redis::get($devKey);

        if ($plainToken === '') {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'No hay token vigente para este email.',
                    'trace_id' => request()->header('X-Trace-Id', (string) Str::orderedUuid()),
                ],
            ], 404);
        }

        return response()->json([
            'token' => $plainToken,
        ]);
    }
}
