<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Exception;

use Domm98CZ\Color\Exception\ConfigurationException;

/**
 * A color space with the same identifier is already registered.
 */
final class DuplicateColorSpaceException extends ConfigurationException
{
    public static function forId(string $id): self
    {
        return new self(\sprintf('A color space with id "%s" is already registered.', $id));
    }
}
