<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Urbania\Properties\Infrastructure\Http\Concerns\HasScopeResolution;
use Urbania\Properties\Infrastructure\Http\Concerns\HasStandardResponses;
use Urbania\Properties\Infrastructure\Http\Resources\CondominiumTreeResource;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;

final readonly class CondominiumTreeController
{
    use HasScopeResolution;
    use HasStandardResponses;

    /**
     * Get the hierarchical tree of a condominium.
     *
     * GET /condominiums/{id}/tree
     *
     * Returns: condominium → towers → property counts.
     *
     * R-09: Tenant isolation.
     * R-09-bis: Only users with org or condominium scope can view tree.
     *   Tower scope is INSUFFICIENT (PANORAMA §5 R-09-bis exception).
     * R-10: Anti-enumeration — 404 for condominiums outside scope.
     *
     * CA 9: Admin sees full tree.
     * CA 15: Residents get 403 (via scope check → 404).
     * CA 17: Tower-only staff get 403 (scope tower is insufficient → 404).
     */
    public function tree(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        // Verify condominium exists and belongs to user's org
        $condominium = EloquentCondominium::query()
            ->where('id', $id)
            ->where('organization_id', $organizationId)
            ->first();

        if ($condominium === null) {
            return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
        }

        // R-09-bis: Only org or condominium scope can view the tree
        // Tower scope is explicitly INSUFFICIENT (R-09-bis exception in PANORAMA §5)
        $condoScope = $this->getCondominiumScope($request);

        if (! $condoScope['all'] && ! in_array($id, $condoScope['ids'], true)) {
            return $this->notFound('CONDOMINIUM_NOT_FOUND', 'Condominio no encontrado.');
        }

        return response()->json([
            'tree' => new CondominiumTreeResource($condominium),
        ]);
    }
}
