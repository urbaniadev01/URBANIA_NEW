<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Repositories;

use Urbania\Auth\Domain\Repositories\InvitationRepositoryInterface;
use Urbania\Auth\Infrastructure\Models\EloquentInvitation;

final readonly class EloquentInvitationRepository implements InvitationRepositoryInterface
{
    public function findValidByToken(string $token): ?EloquentInvitation
    {
        $invitation = EloquentInvitation::where('token', $token)->first();

        if ($invitation === null) {
            return null;
        }

        if (! $invitation->isVigente()) {
            return null;
        }

        if ($invitation->isExpired()) {
            return null;
        }

        return $invitation;
    }

    public function markConsumed(EloquentInvitation $invitation): void
    {
        $invitation->update(['estado' => 'consumida']);
    }
}
