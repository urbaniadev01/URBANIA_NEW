<?php

declare(strict_types=1);

namespace Urbania\Shared\Domain;

abstract class DomainException extends \DomainException
{
    abstract public function getErrorCode(): string;

    abstract public function getHttpStatusCode(): int;
}
