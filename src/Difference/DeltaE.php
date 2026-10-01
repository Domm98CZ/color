<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Difference;

use Domm98CZ\Color\Difference\Algorithm\Cie76;
use Domm98CZ\Color\Difference\Algorithm\Ciede2000;
use Domm98CZ\Color\Difference\Algorithm\DeltaEOk;
use Domm98CZ\Color\Space\Definition\LabSpace;

/**
 * Factories of the built-in color-difference metrics. The metric is always chosen explicitly;
 * CIELAB-based metrics work in CSS Lab (D50) unless another CIELAB space is passed.
 *
 *     DeltaE::ciede2000()->difference($reference, $sample)->value;
 */
final class DeltaE
{
    private function __construct() {}

    public static function cie76(?LabSpace $lab = null): Cie76
    {
        return new Cie76($lab);
    }

    public static function ciede2000(float $kL = 1.0, float $kC = 1.0, float $kH = 1.0, ?LabSpace $lab = null): Ciede2000
    {
        return new Ciede2000($kL, $kC, $kH, $lab);
    }

    public static function ok(): DeltaEOk
    {
        return new DeltaEOk();
    }
}
