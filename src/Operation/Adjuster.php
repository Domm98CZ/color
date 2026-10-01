<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Operation;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Operation\Exception\InvalidOperationException;
use Domm98CZ\Color\Operation\Internal\Values;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Channel adjustments with an explicit model: the color is converted to the model's space, one
 * channel is changed, and the result is converted back to the space of the input color.
 *
 * - Nothing is clipped: a result may be outside the gamut of its space (map it explicitly).
 * - A missing channel counts as 0 in arithmetic (CSS Color 4 § 4.4), except that a missing
 *   (powerless) hue stays missing when rotated: an undefined hue has no rotation.
 * - The alpha is kept, a missing alpha included.
 */
final readonly class Adjuster
{
    /**
     * Adds $by to the lightness, in the units of the model (OKLCh L 0–1; CIE L* and HSL l 0–100).
     *
     * @throws InvalidOperationException when $by is not finite
     */
    public function shiftLightness(Color $color, float $by, LightnessModel $model): Color
    {
        Values::finite($by, 'The lightness change');

        return self::change($color, $model->space(), $model->channel(), static fn(?float $value): float => ($value ?? 0.0) + $by);
    }

    /**
     * Sets the lightness to $value, in the units of the model.
     *
     * @throws InvalidOperationException when $value is not finite
     */
    public function withLightness(Color $color, float $value, LightnessModel $model): Color
    {
        Values::finite($value, 'The lightness');

        return self::change($color, $model->space(), $model->channel(), static fn(): float => $value);
    }

    /**
     * Multiplies the chroma by $scale (0 = gray of the same lightness, 1 = unchanged).
     *
     * @throws InvalidOperationException when $scale is negative or not finite
     */
    public function scaleChroma(Color $color, float $scale, ChromaModel $model): Color
    {
        if (!(Values::finite($scale, 'The chroma scale') >= 0.0)) {
            throw new InvalidOperationException(\sprintf('The chroma scale must be ≥ 0, %s given.', \var_export($scale, true)));
        }

        return self::change($color, $model->space(), 1, static fn(?float $value): float => ($value ?? 0.0) * $scale);
    }

    /**
     * Rotates the hue by $degrees (positive = increasing hue angle).
     *
     * @throws InvalidOperationException when $degrees is not finite
     */
    public function rotateHue(Color $color, float $degrees, HueModel $model): Color
    {
        Values::finite($degrees, 'The hue rotation');

        return self::change($color, $model->space(), $model->channel(), static fn(?float $value): ?float => $value === null ? null : $value + $degrees);
    }

    /**
     * @param \Closure(?float): ?float $change
     */
    private static function change(Color $color, ColorSpace $space, int $channel, \Closure $change): Color
    {
        $coordinates = $color->to($space)->coordinates();
        $coordinates[$channel] = $change($coordinates[$channel]);

        return Color::of($space, $coordinates, Values::alpha($color))->to($color->space());
    }
}
