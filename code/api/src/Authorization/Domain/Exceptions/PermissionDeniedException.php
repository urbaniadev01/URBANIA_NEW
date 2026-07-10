<?php

declare(strict_types=1);

namespace Urbania\Authorization\Domain\Exceptions;

use RuntimeException;

final class PermissionDeniedException extends RuntimeException
{
    public function __construct(string $message = 'No tiene permisos para realizar esta acción.')
    {
        parent::__construct($message);
    }
}
