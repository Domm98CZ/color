<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Palette\Exception\InvalidPaletteException;
use Domm98CZ\Color\Palette\Scale\LightnessScale;
use Domm98CZ\Color\Palette\Scale\MixKind;
use Domm98CZ\Color\Palette\Scale\MixScale;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Factories of scale generators. The space is always explicit.
 *
 *     Scales::lightnessSteps(ColorSpaces::oklch(), [0.97, 0.93, 0.85, 0.75, 0.65, 0.55, 0.45, 0.35, 0.25])
 *         ->generate($brand, GamutMappers::cssColor4())->palette->withNames(['50', '100', …, '900']);
 */
final class Scales
{
    private function __construct() {}

    /**
     * @throws InvalidPaletteException
     */
    public static function tints(ColorSpace $space, int $steps): MixScale
    {
        return new MixScale(MixKind::Tint, $space, $steps);
    }

    /**
     * @throws InvalidPaletteException
     */
    public static function shades(ColorSpace $space, int $steps): MixScale
    {
        return new MixScale(MixKind::Shade, $space, $steps);
    }

    /**
     * @param Color|null $gray default: the neutral with the base's OKLab lightness
     *
     * @throws InvalidPaletteException
     */
    public static function tones(ColorSpace $space, int $steps, ?Color $gray = null): MixScale
    {
        return new MixScale(MixKind::Tone, $space, $steps, $gray);
    }

    /**
     * @param non-empty-list<float> $lightness
     *
     * @throws InvalidPaletteException
     */
    public static function lightnessSteps(ColorSpace $space, array $lightness): LightnessScale
    {
        return new LightnessScale($space, $lightness);
    }
}
