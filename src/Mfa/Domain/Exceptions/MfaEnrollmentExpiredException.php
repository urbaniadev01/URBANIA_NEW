<?php

declare(strict_types=1);

namespace Urbania\Mfa\Domain\Exceptions;

use Urbania\Shared\Domain\DomainException;

final class MfaEnrollmentExpiredException extends DomainException
{
    public function __construct()
    {
        parent::__construct('El enrollment de MFA ha expirado por demasiados intentos fallidos.');
    }

    public function getErrorCode(): string
    {
        return 'MFA_ENROLLMENT_EXPIRED';
    }

    public function getHttpStatusCode(): int
    {
        return 422;
    }
}
