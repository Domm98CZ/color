<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Math\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * A tolerance bound is negative or not finite.
 */
final class InvalidToleranceException extends InvalidArgumentException
{
    public static function for(string $bound, float $value): self
    {
        return new self(\sprintf('The %s tolerance must be a finite number >= 0, %s given.', $bound, \var_export($value, true)));
    }
}
