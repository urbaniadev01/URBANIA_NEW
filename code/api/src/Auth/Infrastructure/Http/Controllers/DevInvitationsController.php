<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Urbania\Auth\Infrastructure\Models\EloquentInvitation;

final readonly class DevInvitationsController
{
    public function last(Request $request): JsonResponse
    {
        $email = $request->query('email');

        if ($email === null || $email === '') {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'El parámetro email es obligatorio.',
                    'trace_id' => request()->header('X-Trace-Id', (string) Str::orderedUuid()),
                ],
            ], 422);
        }

        $invitation = EloquentInvitation::where('email', $email)
            ->where('estado', 'vigente')
            ->where('expira_en', '>', now())
            ->orderBy('created_at', 'desc')
            ->first();

        if ($invitation === null) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'No hay invitación vigente para ese email.',
                    'trace_id' => request()->header('X-Trace-Id', (string) Str::orderedUuid()),
                ],
            ], 404);
        }

        return response()->json([
            'invitation_token' => $invitation->token,
        ]);
    }
}
