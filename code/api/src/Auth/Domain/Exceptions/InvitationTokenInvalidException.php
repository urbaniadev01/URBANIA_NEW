<?php

declare(strict_types=1);

namespace Urbania\Auth\Domain\Exceptions;

use RuntimeException;

final class InvitationTokenInvalidException extends RuntimeException
{
    public function __construct(string $message = 'El token de invitación no es válido o ya fue usado.')
    {
        parent::__construct($message);
    }
}
