<?php

declare(strict_types=1);

namespace Urbania\Auth\Domain\Exceptions;

use RuntimeException;

final class EmailAlreadyRegisteredException extends RuntimeException
{
    public function __construct(string $message = 'El email ya está asociado a un usuario existente.')
    {
        parent::__construct($message);
    }
}
