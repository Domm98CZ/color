<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Colorimetry;

use Domm98CZ\Color\Colorimetry\Algorithm\Bradford;

/**
 * Factory of the built-in chromatic adaptation transform: linear Bradford, complete adaptation, the
 * default of conversions (CSS Color 4). Another transform implements
 * {@see Contract\ChromaticAdaptationTransform} and is passed in `Space\ConversionOptions`.
 */
final class ChromaticAdaptations
{
    private function __construct() {}

    public static function bradford(): Bradford
    {
        return new Bradford();
    }
}
