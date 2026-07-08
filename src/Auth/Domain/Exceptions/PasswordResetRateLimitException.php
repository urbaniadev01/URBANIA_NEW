<?php

declare(strict_types=1);

namespace Urbania\Auth\Domain\Exceptions;

use Urbania\Shared\Domain\DomainException;

final class PasswordResetRateLimitException extends DomainException
{
    public function __construct(string $message = 'Demasiados intentos. Intente nuevamente más tarde.')
    {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return 'TOO_MANY_REQUESTS';
    }

    public function getHttpStatusCode(): int
    {
        return 429;
    }
}
