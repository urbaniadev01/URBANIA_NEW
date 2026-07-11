<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Repositories;

use Urbania\Auth\Domain\Repositories\ContactRepositoryInterface;
use Urbania\Auth\Infrastructure\Models\EloquentContact;

final readonly class EloquentContactRepository implements ContactRepositoryInterface
{
    /**
     * @param array{organization_id: string, user_id: string, nombre: string, email: string, telefono?: string|null} $data
     */
    public function create(array $data): EloquentContact
    {
        $contact = new EloquentContact($data);
        $contact->save();

        return $contact;
    }
}
