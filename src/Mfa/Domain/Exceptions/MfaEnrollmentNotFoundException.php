<?php

declare(strict_types=1);

namespace Urbania\Mfa\Domain\Exceptions;

use Urbania\Shared\Domain\DomainException;

final class MfaEnrollmentNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct('No hay un enrollment de MFA pendiente.');
    }

    public function getErrorCode(): string
    {
        return 'MFA_ENROLLMENT_NOT_FOUND';
    }

    public function getHttpStatusCode(): int
    {
        return 404;
    }
}
