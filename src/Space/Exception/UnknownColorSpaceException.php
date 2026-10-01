<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * No color space with the requested identifier is registered.
 */
final class UnknownColorSpaceException extends InvalidArgumentException
{
    public static function forId(string $id): self
    {
        return new self(\sprintf('Unknown color space "%s".', $id));
    }
}
