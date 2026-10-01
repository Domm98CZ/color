<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette\Contract;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Gamut\Contract\GamutMapper;
use Domm98CZ\Color\Palette\Result\GeneratedScale;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Generates a scale (an ordered palette) from a base color.
 */
interface ScaleGenerator
{
    /**
     * @param GamutMapper|null $mapper when given, every step is mapped into $gamut (default sRGB)
     *                                 and the mapping is reported; otherwise nothing is mapped
     */
    public function generate(Color $base, ?GamutMapper $mapper = null, ?ColorSpace $gamut = null): GeneratedScale;
}
