<?php

declare(strict_types=1);

namespace Urbania\Auth\Domain\Repositories;

use Urbania\Auth\Infrastructure\Models\EloquentOrganization;

interface OrganizationRepositoryInterface
{
    public function findById(string $id): ?EloquentOrganization;
}
