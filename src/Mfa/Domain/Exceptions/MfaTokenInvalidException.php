<?php

declare(strict_types=1);

namespace Urbania\Mfa\Domain\Exceptions;

use Urbania\Shared\Domain\DomainException;

final class MfaTokenInvalidException extends DomainException
{
    public function __construct()
    {
        parent::__construct('El token MFA no es válido o ha expirado.');
    }

    public function getErrorCode(): string
    {
        return 'MFA_TOKEN_INVALID';
    }

    public function getHttpStatusCode(): int
    {
        return 401;
    }
}
