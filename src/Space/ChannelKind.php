<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space;

/**
 * How a channel value is to be interpreted.
 */
enum ChannelKind: string
{
    /** A plain number (RGB component, L*, a*, OKLab a…). */
    case Number = 'number';

    /** A hue angle in degrees, normalised to [0, 360); may be missing (null) when powerless. */
    case Hue = 'hue';

    /** A number on a 0–100 scale that CSS writes as a percentage (HSL saturation, HWB whiteness…). */
    case Percentage = 'percentage';
}
