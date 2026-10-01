<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Colorimetry;

/**
 * Built-in white points. Every method returns the same immutable instance on each call.
 *
 * D65 and D50 are defined exactly as in CSS Color 4 (CRD 2026-09-26, sample code):
 * from 4-digit CIE xy chromaticities, D65 = (0.3127, 0.3290), D50 = (0.3457, 0.3585).
 * Other definitions of these illuminants (5-digit CIE values, the ICC PCS D50 XYZ) differ slightly
 * and will be added as separately named white points when needed.
 */
final class WhitePoints
{
    private static ?WhitePoint $d65 = null;
    private static ?WhitePoint $d50 = null;

    private function __construct() {}

    public static function d65(): WhitePoint
    {
        return self::$d65 ??= WhitePoint::fromChromaticity('D65', new Chromaticity(0.3127, 0.3290));
    }

    public static function d50(): WhitePoint
    {
        return self::$d50 ??= WhitePoint::fromChromaticity('D50', new Chromaticity(0.3457, 0.3585));
    }
}
