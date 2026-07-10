<?php

declare(strict_types=1);

namespace Urbania\Properties\Domain\Exceptions;

final class PropertyNotInCondominiumException extends \DomainException
{
    public function __construct(
        public readonly string $propertyId,
        public readonly string $condominiumId,
    ) {
        parent::__construct(
            "Property {$propertyId} does not belong to condominium {$condominiumId}.",
        );
    }
}
