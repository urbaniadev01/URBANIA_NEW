<?php

declare(strict_types=1);

namespace Urbania\Auth\Domain\Exceptions;

use Urbania\Shared\Domain\DomainException;

final class ResetTokenExpiredException extends DomainException
{
    public function __construct()
    {
        parent::__construct('El token de recuperación ha expirado.');
    }

    public function getErrorCode(): string
    {
        return 'RESET_TOKEN_EXPIRED';
    }

    public function getHttpStatusCode(): int
    {
        return 422;
    }
}
