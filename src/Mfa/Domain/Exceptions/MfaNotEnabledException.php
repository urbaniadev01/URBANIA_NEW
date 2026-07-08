<?php

declare(strict_types=1);

namespace Urbania\Mfa\Domain\Exceptions;

use Urbania\Shared\Domain\DomainException;

final class MfaNotEnabledException extends DomainException
{
    public function __construct()
    {
        parent::__construct('MFA no está activado para este usuario.');
    }

    public function getErrorCode(): string
    {
        return 'MFA_NOT_ENABLED';
    }

    public function getHttpStatusCode(): int
    {
        return 409;
    }
}
