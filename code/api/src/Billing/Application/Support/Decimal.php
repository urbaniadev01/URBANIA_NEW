<?php

declare(strict_types=1);

namespace Urbania\Billing\Application\Support;

use UnexpectedValueException;

/**
 * Conversión segura de atributos numéricos de Eloquent (que llegan como `mixed`:
 * el cast `decimal:2` devuelve string, un `integer` devuelve int, etc.) a float/int
 * de PHP.
 *
 * No es un cast ciego para silenciar al analizador: las columnas de dinero
 * (`valor_total`, `saldo`, `valor_base`) y de coeficiente son `NOT NULL` numéricas en
 * la BD, así que recibir algo no numérico acá significa que el esquema o el cast del
 * modelo cambió — un bug real que debe fallar ruidosamente en el prorrateo, no
 * degradar silenciosamente a 0 y facturar de menos.
 */
final class Decimal
{
    public static function toFloat(mixed $value, string $context = 'valor'): float
    {
        if (! is_numeric($value)) {
            throw new UnexpectedValueException(
                sprintf('Se esperaba un %s numérico, se recibió %s.', $context, get_debug_type($value)),
            );
        }

        return (float) $value;
    }

    public static function toInt(mixed $value, string $context = 'valor'): int
    {
        if (! is_numeric($value)) {
            throw new UnexpectedValueException(
                sprintf('Se esperaba un %s numérico, se recibió %s.', $context, get_debug_type($value)),
            );
        }

        return (int) $value;
    }
}
