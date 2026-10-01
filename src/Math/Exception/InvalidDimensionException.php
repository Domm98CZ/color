<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Math\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * An array does not have the shape required for a vector or matrix.
 */
final class InvalidDimensionException extends InvalidArgumentException
{
    public static function expected(string $what, string $shape): self
    {
        return new self(\sprintf('%s must be %s.', $what, $shape));
    }
}
