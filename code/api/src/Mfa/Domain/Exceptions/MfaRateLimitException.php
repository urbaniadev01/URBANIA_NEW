<?php

declare(strict_types=1);

namespace Urbania\Mfa\Domain\Exceptions;

use Urbania\Shared\Domain\DomainException;

final class MfaRateLimitException extends DomainException
{
    public function __construct(string $message = 'Demasiados intentos. Intente nuevamente más tarde.')
    {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return 'MFA_RATE_LIMIT';
    }

    public function getHttpStatusCode(): int
    {
        return 429;
    }
}
