<?php

declare(strict_types=1);

namespace Urbania\Mfa\Domain\Exceptions;

use Urbania\Shared\Domain\DomainException;

final class MfaCodeInvalidException extends DomainException
{
    public function __construct()
    {
        parent::__construct('El código MFA ingresado no es válido.');
    }

    public function getErrorCode(): string
    {
        return 'MFA_CODE_INVALID';
    }

    public function getHttpStatusCode(): int
    {
        return 422;
    }
}
