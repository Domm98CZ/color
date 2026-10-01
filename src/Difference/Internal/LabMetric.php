<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Difference\Internal;

use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Definition\LabSpace;

/**
 * @internal the CIELAB space a metric works in and how it appears in the algorithm variant
 */
final class LabMetric
{
    private function __construct() {}

    /**
     * CSS Lab (D50) unless another CIELAB space is given (e.g. ColorSpaces::labD65()).
     */
    public static function space(?LabSpace $lab): LabSpace
    {
        return $lab ?? ColorSpaces::lab();
    }

    /**
     * @return non-empty-string e.g. "white=D50"
     */
    public static function variant(LabSpace $lab): string
    {
        return 'white=' . $lab->whitePoint()->name;
    }
}
