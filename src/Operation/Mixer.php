<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Operation;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Interpolation\HueInterpolation;
use Domm98CZ\Color\Interpolation\InterpolationMethod;
use Domm98CZ\Color\Interpolation\MissingComponents;
use Domm98CZ\Color\Interpolation\Segment;
use Domm98CZ\Color\Operation\Exception\InvalidOperationException;
use Domm98CZ\Color\Operation\Internal\Values;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Mixes colors in an explicit space, following CSS Color 4 § 13 (premultiplied alpha, missing
 * components, hue methods); see {@see Segment}.
 *
 * The default space is OKLab, the CSS default (part of the compatibility promise). `new
 * Mixer(ColorSpaces::srgb())` mixes like Sass `mix()` and legacy CSS (gamma-encoded sRGB);
 * `ColorSpaces::srgbLinear()` mixes light physically. The result is in the mixing space and is
 * never clipped; convert it with `->to()` and map it with a gamut mapper when needed.
 */
final readonly class Mixer
{
    public InterpolationMethod $method;

    public function __construct(?ColorSpace $space = null, HueInterpolation $hue = HueInterpolation::Shorter)
    {
        $this->method = new InterpolationMethod($space ?? ColorSpaces::oklab(), $hue);
    }

    /**
     * $weight is the amount of $b in [0, 1]: 0 gives $a, 1 gives $b, 0.25 is 75 % $a and 25 % $b.
     *
     * @throws InvalidOperationException when the weight is not in [0, 1]
     */
    public function mix(Color $a, Color $b, float $weight = 0.5): Color
    {
        $weight = Values::fraction($weight, 'The mix weight');

        return Values::color(Segment::between(Values::of($a), Values::of($b), $this->method)->at($weight));
    }

    /**
     * Weighted mix of several colors: `[[$a, 1.0], [$b, 2.0]]` is one third $a, two thirds $b.
     * The colors are mixed in the given order, as CSS `color-mix()` does (§ 3.3); in a space with a
     * hue the order changes the result.
     *
     * @param non-empty-list<array{Color, float}> $items colors with weights ≥ 0, not all 0
     *
     * @throws InvalidOperationException when the list is empty or a weight is invalid
     */
    public function mixMany(array $items): Color
    {
        if ($items === []) {
            throw new InvalidOperationException('mixMany() needs at least one color.');
        }

        $total = 0.0;
        foreach ($items as [, $weight]) {
            if (!($weight >= 0.0) || !\is_finite($weight)) {
                throw new InvalidOperationException(\sprintf('Mix weights must be finite numbers ≥ 0, %s given.', \var_export($weight, true)));
            }
            $total += $weight;
        }

        if ($total <= 0.0) {
            throw new InvalidOperationException('At least one mix weight must be greater than 0.');
        }

        $color = MissingComponents::convert(Values::of($items[0][0]), $this->method->space, $this->method->conversion);
        $accumulated = $items[0][1];

        for ($index = 1, $count = \count($items); $index < $count; $index++) {
            [$next, $weight] = $items[$index];
            $combined = $accumulated + $weight;
            $progress = $combined > 0.0 ? $weight / $combined : 0.5;
            $color = Segment::between($color, Values::of($next), $this->method)->at($progress);
            $accumulated = $combined;
        }

        return Values::color($color);
    }
}
