<?php

declare(strict_types=1);

namespace Urbania\Auth\Domain\Exceptions;

use RuntimeException;

final class InvalidCredentialsException extends RuntimeException
{
    public function __construct(string $message = 'Credenciales inválidas.')
    {
        parent::__construct($message);
    }
}
