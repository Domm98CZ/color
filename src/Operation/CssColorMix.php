<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Operation;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Interpolation\ColorMix;
use Domm98CZ\Color\Interpolation\Exception\InvalidInterpolationException;
use Domm98CZ\Color\Interpolation\HueInterpolation;
use Domm98CZ\Color\Interpolation\InterpolationMethod;
use Domm98CZ\Color\Interpolation\MixItem;
use Domm98CZ\Color\Operation\Internal\Values;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * CSS `color-mix()` with all of its semantics (CSS Color 5, WD 13 September 2026, § 3):
 * percentage normalisation, the alpha multiplier when the percentages sum to less than 100 %,
 * missing components. It gives the same color as `Color::parse('color-mix(…)')`.
 *
 *     CssColorMix::mix(ColorSpaces::oklch(), HueInterpolation::Longer, [$a, 0.3], [$b, null]);
 *     // = color-mix(in oklch longer hue, <a> 30%, <b>)
 *
 * Percentages are fractions in [0, 1]; null means "not specified". See {@see ColorMix} for the
 * algorithm. For plain two-color mixing without the CSS percentage rules, use {@see Mixer}.
 */
final class CssColorMix
{
    private function __construct() {}

    /**
     * @param array{Color, float|null} ...$items
     *
     * @throws InvalidInterpolationException when there are no items or a percentage is outside [0, 1]
     */
    public static function mix(ColorSpace $space, HueInterpolation $hue, array ...$items): Color
    {
        $mixItems = [];
        foreach ($items as [$color, $percentage]) {
            $mixItems[] = new MixItem(Values::of($color), $percentage);
        }

        return Values::color(ColorMix::mix(new InterpolationMethod($space, $hue), $mixItems));
    }
}
