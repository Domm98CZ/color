<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Internal;

use Domm98CZ\Color\Space\Exception\InvalidColorSpaceDefinitionException;

/**
 * @internal validation of color space identifiers and names
 */
final class SpaceIdentifier
{
    private const string PATTERN = '~^[a-z0-9]+(?:[-@.][a-z0-9]+)*$~';

    private function __construct() {}

    /**
     * @return non-empty-string
     */
    public static function id(string $id): string
    {
        if (\preg_match(self::PATTERN, $id) !== 1) {
            throw new InvalidColorSpaceDefinitionException(\sprintf(
                'A color space id must be lowercase alphanumeric words separated by "-", "@" or ".", "%s" given.',
                $id,
            ));
        }

        return $id;
    }

    /**
     * @return non-empty-string
     */
    public static function name(string $name): string
    {
        if (\trim($name) === '') {
            throw new InvalidColorSpaceDefinitionException('A color space name must not be empty.');
        }

        return $name;
    }
}
