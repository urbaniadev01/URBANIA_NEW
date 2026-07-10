<?php

declare(strict_types=1);

namespace Urbania\Auth\Domain\Exceptions;

use RuntimeException;

final class RefreshTokenExpiredException extends RuntimeException
{
    public function __construct(string $message = 'El refresh token ha expirado.')
    {
        parent::__construct($message);
    }
}
