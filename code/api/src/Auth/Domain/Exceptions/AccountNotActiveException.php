<?php

declare(strict_types=1);

namespace Urbania\Auth\Domain\Exceptions;

use RuntimeException;

final class AccountNotActiveException extends RuntimeException
{
    public function __construct(string $message = 'La cuenta no está activa.')
    {
        parent::__construct($message);
    }
}
