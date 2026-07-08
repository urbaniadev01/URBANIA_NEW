<?php

declare(strict_types=1);

namespace Urbania\Mfa\Domain\Exceptions;

use Urbania\Shared\Domain\DomainException;

final class MfaAlreadyEnabledException extends DomainException
{
    public function __construct()
    {
        parent::__construct('MFA ya está activado para este usuario.');
    }

    public function getErrorCode(): string
    {
        return 'MFA_ALREADY_ENABLED';
    }

    public function getHttpStatusCode(): int
    {
        return 409;
    }
}
