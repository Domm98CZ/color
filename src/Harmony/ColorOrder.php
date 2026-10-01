<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Harmony;

/**
 * The place of a color on a wheel with primaries.
 */
enum ColorOrder: string
{
    case Primary = 'primary';
    /** A mixture of two neighbouring primaries. */
    case Secondary = 'secondary';
    /** A mixture of a primary and a neighbouring secondary. */
    case Tertiary = 'tertiary';
    /** No hue (gray, black, white): it has no place on the wheel. */
    case Achromatic = 'achromatic';
}
