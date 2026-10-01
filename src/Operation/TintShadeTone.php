<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Operation;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Interpolation\HueInterpolation;
use Domm98CZ\Color\Operation\Exception\InvalidOperationException;
use Domm98CZ\Color\Operation\Internal\Values;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Tints, shades and tones in the traditional sense: a mix with white, black or gray, in an
 * explicit mixing space (there is no default: the result depends on it strongly).
 *
 * - White and black are sRGB white (the D65 white) and black.
 * - The default gray of {@see tone()} is the neutral with the same OKLab lightness as the color
 *   (so a tone keeps the OK L when mixed in OKLab or OKLCh).
 * - The white, black or gray takes the alpha of the color, so a tint does not change the opacity.
 * - The result is in the mixing space (see {@see Mixer}).
 */
final readonly class TintShadeTone
{
    private Mixer $mixer;

    public function __construct(ColorSpace $space, HueInterpolation $hue = HueInterpolation::Shorter)
    {
        $this->mixer = new Mixer($space, $hue);
    }

    /**
     * @param float $amount amount of white in [0, 1]
     *
     * @throws InvalidOperationException
     */
    public function tint(Color $color, float $amount): Color
    {
        return $this->mixer->mix($color, Color::of(ColorSpaces::srgb(), [1.0, 1.0, 1.0], Values::alpha($color)), Values::fraction($amount, 'The tint amount'));
    }

    /**
     * @param float $amount amount of black in [0, 1]
     *
     * @throws InvalidOperationException
     */
    public function shade(Color $color, float $amount): Color
    {
        return $this->mixer->mix($color, Color::of(ColorSpaces::srgb(), [0.0, 0.0, 0.0], Values::alpha($color)), Values::fraction($amount, 'The shade amount'));
    }

    /**
     * @param float $amount amount of gray in [0, 1]
     * @param Color|null $gray the gray to mix with; default: the neutral with the same OKLab L
     *
     * @throws InvalidOperationException
     */
    public function tone(Color $color, float $amount, ?Color $gray = null): Color
    {
        $gray ??= Color::of(ColorSpaces::oklab(), [$color->to(ColorSpaces::oklab())->coordinates()[0], 0.0, 0.0], Values::alpha($color));

        return $this->mixer->mix($color, $gray, Values::fraction($amount, 'The tone amount'));
    }
}
