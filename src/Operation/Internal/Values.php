<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Operation\Internal;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Operation\Exception\InvalidOperationException;
use Domm98CZ\Color\Space\ColorValue;

/**
 * @internal bridges between Color and the ColorValue the Interpolation module computes with
 */
final class Values
{
    private function __construct() {}

    public static function of(Color $color): ColorValue
    {
        return new ColorValue($color->space(), $color->coordinates(), $color->isAlphaMissing() ? null : $color->alpha());
    }

    public static function color(ColorValue $value): Color
    {
        return Color::of($value->space, $value->coordinates, $value->alpha);
    }

    /**
     * The alpha of $color, null when missing.
     */
    public static function alpha(Color $color): ?float
    {
        return $color->isAlphaMissing() ? null : $color->alpha();
    }

    /**
     * @throws InvalidOperationException when $value is not a finite number in [0, 1]
     */
    public static function fraction(float $value, string $name): float
    {
        if (!($value >= 0.0 && $value <= 1.0)) {
            throw new InvalidOperationException(\sprintf('%s must be in [0, 1], %s given.', $name, \var_export($value, true)));
        }

        return $value;
    }

    /**
     * @throws InvalidOperationException when $value is not finite
     */
    public static function finite(float $value, string $name): float
    {
        if (!\is_finite($value)) {
            throw new InvalidOperationException(\sprintf('%s must be a finite number, %s given.', $name, \var_export($value, true)));
        }

        return $value;
    }
}
