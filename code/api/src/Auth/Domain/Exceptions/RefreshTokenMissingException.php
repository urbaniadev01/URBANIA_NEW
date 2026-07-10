<?php

declare(strict_types=1);

namespace Urbania\Auth\Domain\Exceptions;

use RuntimeException;

final class RefreshTokenMissingException extends RuntimeException
{
    public function __construct(string $message = 'No se envió el refresh token.')
    {
        parent::__construct($message);
    }
}
