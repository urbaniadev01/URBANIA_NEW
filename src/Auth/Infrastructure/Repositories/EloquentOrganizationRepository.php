<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Repositories;

use Urbania\Auth\Domain\Repositories\OrganizationRepositoryInterface;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;

final readonly class EloquentOrganizationRepository implements OrganizationRepositoryInterface
{
    public function findById(string $id): ?EloquentOrganization
    {
        return EloquentOrganization::find($id);
    }
}
