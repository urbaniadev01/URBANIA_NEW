<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Http\Concerns;

use Illuminate\Http\Request;
use Urbania\Authorization\Infrastructure\Models\EloquentRoleAssignment;

trait HasScopeResolution
{
    /**
     * Get the user's effective scope for condominium access.
     *
     * @return array{all: bool, ids: string[]}
     */
    private function getCondominiumScope(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return ['all' => false, 'ids' => []];
        }

        $assignments = EloquentRoleAssignment::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->get();

        $hasOrgScope = $assignments->contains(
            fn (EloquentRoleAssignment $a): bool => $a->scope_type === 'organization',
        );

        if ($hasOrgScope) {
            return ['all' => true, 'ids' => []];
        }

        $ids = $assignments
            ->filter(fn (EloquentRoleAssignment $a): bool => $a->scope_type === 'condominium' && $a->scope_id !== null)
            ->pluck('scope_id')
            ->unique()
            ->values()
            ->toArray();

        return ['all' => false, 'ids' => $ids];
    }

    /**
     * Get raw tower scope IDs (without broadening via org/condo).
     *
     * @return string[]
     */
    private function getRawTowerScopeIds(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return EloquentRoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('scope_type', 'tower')
            ->whereNotNull('scope_id')
            ->whereNull('deleted_at')
            ->pluck('scope_id')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get the user's unit scope — property IDs the user is assigned as a resident.
     *
     * @return string[]
     */
    private function getUnitScope(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return EloquentRoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('scope_type', 'unit')
            ->whereNotNull('scope_id')
            ->whereNull('deleted_at')
            ->pluck('scope_id')
            ->unique()
            ->values()
            ->toArray();
    }
}
