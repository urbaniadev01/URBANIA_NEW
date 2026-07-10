<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

trait HasStandardResponses
{
    /**
     * Return a standardized 404 Not Found JSON response.
     */
    private function notFound(string $code, string $message): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'trace_id' => (string) Str::orderedUuid(),
            ],
        ], 404);
    }
}
