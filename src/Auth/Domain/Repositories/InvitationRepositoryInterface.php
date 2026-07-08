<?php

declare(strict_types=1);

namespace Urbania\Auth\Domain\Repositories;

use Urbania\Auth\Infrastructure\Models\EloquentInvitation;

interface InvitationRepositoryInterface
{
    public function findValidByToken(string $token): ?EloquentInvitation;

    public function markConsumed(EloquentInvitation $invitation): void;
}
