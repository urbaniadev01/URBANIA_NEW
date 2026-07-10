<?php

declare(strict_types=1);

namespace Urbania\Auth\Domain\Exceptions;

use Urbania\Shared\Domain\DomainException;

final class ResetTokenInvalidException extends DomainException
{
    public function __construct()
    {
        parent::__construct('El token de recuperación no es válido o ya fue usado.');
    }

    public function getErrorCode(): string
    {
        return 'RESET_TOKEN_INVALID';
    }

    public function getHttpStatusCode(): int
    {
        return 422;
    }
}
