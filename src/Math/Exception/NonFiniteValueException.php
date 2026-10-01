<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Math\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * A value that must be a finite number is NaN or ±INF.
 */
final class NonFiniteValueException extends InvalidArgumentException
{
    public static function for(string $name, float $value): self
    {
        return new self(\sprintf('%s must be a finite number, %s given.', $name, self::describe($value)));
    }

    private static function describe(float $value): string
    {
        if (\is_nan($value)) {
            return 'NAN';
        }

        return $value > 0 ? 'INF' : '-INF';
    }
}
