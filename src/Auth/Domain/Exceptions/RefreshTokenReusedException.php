<?php

declare(strict_types=1);

namespace Urbania\Auth\Domain\Exceptions;

use RuntimeException;

final class RefreshTokenReusedException extends RuntimeException
{
    public function __construct(string $message = 'El refresh token ya fue usado. Todas las sesiones fueron revocadas.')
    {
        parent::__construct($message);
    }
}
