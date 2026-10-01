<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette\Internal;

/**
 * @internal
 */
final class Numbers
{
    private function __construct() {}

    /**
     * A number for messages: at most six decimals, no trailing zeros ("4.4", "0.05", "300").
     */
    public static function format(float $value): string
    {
        return \rtrim(\rtrim(\sprintf('%.6F', $value), '0'), '.');
    }
}
