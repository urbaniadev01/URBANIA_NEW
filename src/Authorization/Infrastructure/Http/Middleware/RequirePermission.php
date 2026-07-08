<?php

declare(strict_types=1);

namespace Urbania\Authorization\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Urbania\Authorization\Application\UseCases\CheckPermissionUseCase;

final readonly class RequirePermission
{
    public function __construct(
        private CheckPermissionUseCase $checkPermission,
    ) {}

    /**
     * Handle the incoming request.
     *
     * Middleware parameters: permission_name,scope_type
     *
     * The scope_id is extracted from a route parameter whose name matches
     * the scope_type (e.g., "organization" → route param {organization}).
     *
     * Usage: ->middleware('require_permission:admin.access,organization')
     *
     * @param string ...$parameters [permission, scope_type]
     */
    public function handle(Request $request, Closure $next, string ...$parameters): Response
    {
        $permission = $parameters[0] ?? null;
        $scopeType = $parameters[1] ?? null;

        if ($permission === null || $scopeType === null) {
            return $this->denied('Middleware configuration error: permission and scope_type are required.');
        }

        $user = $request->user();

        if ($user === null) {
            return $this->denied('Usuario no autenticado.');
        }

        $userId = (string) $user->id;

        // Extract scope_id from route parameter matching scope_type
        $scopeId = $request->route($scopeType);

        if ($scopeId !== null && ! is_string($scopeId)) {
            $scopeId = (string) $scopeId;
        }

        $hasPermission = $this->checkPermission->execute(
            userId: $userId,
            permission: $permission,
            scopeType: $scopeType,
            scopeId: $scopeId,
        );

        if (! $hasPermission) {
            return $this->denied('No tiene permisos para realizar esta acción.');
        }

        return $next($request);
    }

    private function denied(string $message): Response
    {
        return response()->json([
            'error' => [
                'code' => 'PERMISSION_DENIED',
                'message' => $message,
                'trace_id' => (string) Str::orderedUuid(),
            ],
        ], 403);
    }
}
