<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette\Scale;

/**
 * What {@see MixScale} mixes the base with: white (tints), black (shades) or a gray (tones).
 */
enum MixKind: string
{
    case Tint = 'tint';
    case Shade = 'shade';
    case Tone = 'tone';
}
