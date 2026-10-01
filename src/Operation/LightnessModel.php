<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Operation;

use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Which "lightness" an adjustment changes. They are different quantities on different scales:
 * HSL lightness is not perceptual (yellow and blue with equal HSL lightness look very different),
 * CIE L* and OKLab L are.
 */
enum LightnessModel: string
{
    /** OKLab/OKLCh L, 0–1 (CSS Color 4 § 9.2). */
    case OklchL = 'oklch-l';

    /** CIE L* of CIELAB with a D50 white (CSS `lab()`/`lch()`), 0–100. */
    case CieLabL = 'cielab-l';

    /** HSL lightness of sRGB, 0–100 (CSS `hsl()`, the Sass-style model). */
    case HslL = 'hsl-l';

    public function space(): ColorSpace
    {
        return match ($this) {
            self::OklchL => ColorSpaces::oklch(),
            self::CieLabL => ColorSpaces::lch(),
            self::HslL => ColorSpaces::hsl(),
        };
    }

    /**
     * The lightness of white in this model (1 for OKLab L, 100 for L* and HSL l); black is 0.
     */
    public function maximum(): float
    {
        return $this === self::OklchL ? 1.0 : 100.0;
    }

    /**
     * Index of the lightness channel in {@see space()}.
     */
    public function channel(): int
    {
        return $this === self::HslL ? 2 : 0;
    }
}
