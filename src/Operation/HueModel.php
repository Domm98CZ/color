<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Operation;

use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * In which space a hue is rotated. The same angle gives different colors: 180° in HSL is the RGB
 * complement, 180° in OKLCh the perceptual opposite.
 */
enum HueModel: string
{
    case Oklch = 'oklch';
    case CieLch = 'cielch';
    case Hsl = 'hsl';

    public function space(): ColorSpace
    {
        return match ($this) {
            self::Oklch => ColorSpaces::oklch(),
            self::CieLch => ColorSpaces::lch(),
            self::Hsl => ColorSpaces::hsl(),
        };
    }

    /**
     * Index of the hue channel in {@see space()}.
     */
    public function channel(): int
    {
        return $this === self::Hsl ? 0 : 2;
    }
}
