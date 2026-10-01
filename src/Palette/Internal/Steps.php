<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette\Internal;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Gamut\Contract\GamutMapper;
use Domm98CZ\Color\Palette\Palette;
use Domm98CZ\Color\Palette\Result\GeneratedScale;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * @internal the optional gamut mapping shared by the scale generators
 */
final class Steps
{
    private function __construct() {}

    /**
     * @param list<Color> $colors
     */
    public static function scale(array $colors, ?GamutMapper $mapper, ?ColorSpace $gamut): GeneratedScale
    {
        if ($mapper === null) {
            return new GeneratedScale(Palette::of(...$colors), \array_fill(0, \count($colors), null));
        }

        $gamut ??= ColorSpaces::srgb();
        $mapping = \array_map(static fn(Color $color) => $mapper->map($color, $gamut), $colors);

        return new GeneratedScale(Palette::of(...\array_map(static fn($result): Color => $result->color, $mapping)), $mapping);
    }
}
