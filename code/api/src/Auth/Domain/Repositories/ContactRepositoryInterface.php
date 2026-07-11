<?php

declare(strict_types=1);

namespace Urbania\Auth\Domain\Repositories;

use Urbania\Auth\Infrastructure\Models\EloquentContact;

interface ContactRepositoryInterface
{
    /**
     * @param array{organization_id: string, user_id: string, nombre: string, email: string, telefono?: string|null} $data
     */
    public function create(array $data): EloquentContact;
}
