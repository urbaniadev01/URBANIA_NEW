<?php

declare(strict_types=1);

namespace Urbania\Authorization\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;

final readonly class AdminController
{
    /**
     * Example protected endpoint: admin dashboard, scoped to an organization.
     *
     * Requires permission "admin.access" at scope "organization" = {organization}.
     * Authorization is handled by the RequirePermission middleware.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'message' => 'Admin dashboard — acceso autorizado.',
        ]);
    }
}
