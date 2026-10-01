<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Operation;

use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Which chroma an adjustment scales. HSL saturation is deliberately not offered: it is not a
 * chroma, and scaling it changes the perceived lightness.
 */
enum ChromaModel: string
{
    /** OKLCh C (CSS Color 4 § 9.2). */
    case Oklch = 'oklch';

    /** CIE LCh C*ab with a D50 white (CSS `lch()`). */
    case CieLch = 'cielch';

    public function space(): ColorSpace
    {
        return $this === self::Oklch ? ColorSpaces::oklch() : ColorSpaces::lch();
    }
}
