<?php

declare(strict_types=1);

namespace Urbania\Mfa\Domain\Exceptions;

use Urbania\Shared\Domain\DomainException;

final class MfaRecoveryCodeUsedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Este código de respaldo ya fue utilizado.');
    }

    public function getErrorCode(): string
    {
        return 'MFA_RECOVERY_CODE_USED';
    }

    public function getHttpStatusCode(): int
    {
        return 422;
    }
}
