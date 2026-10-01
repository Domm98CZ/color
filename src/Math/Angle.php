<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Math;

/**
 * Angle helpers. Hue angles in this library are always in degrees.
 */
final class Angle
{
    private function __construct() {}

    /**
     * Normalises an angle to the half-open interval [0, 360).
     *
     * -30 → 330, 360 → 0, 725 → 5. Negative zero becomes positive zero.
     */
    public static function normalizeDegrees(float $degrees): float
    {
        Numeric::assertFinite($degrees, 'Angle');

        $normalized = \fmod($degrees, 360.0);

        if ($normalized < 0.0) {
            $normalized += 360.0;
        }

        // Adding 360 to a tiny negative remainder can round up to exactly 360.
        if ($normalized >= 360.0) {
            $normalized = 0.0;
        }

        return Numeric::normalizeZero($normalized);
    }

    /**
     * Signed shortest rotation from $from to $to, in the interval (-180, 180].
     *
     * A difference of exactly 180° is reported as +180 (the direction is ambiguous;
     * callers that care, such as hue interpolation, must decide explicitly).
     */
    public static function signedDifferenceDegrees(float $from, float $to): float
    {
        $difference = self::normalizeDegrees($to - $from);

        return $difference > 180.0 ? $difference - 360.0 : $difference;
    }

    /**
     * Unsigned shortest angular distance, in the interval [0, 180].
     */
    public static function distanceDegrees(float $a, float $b): float
    {
        return \abs(self::signedDifferenceDegrees($a, $b));
    }

    public static function degreesToRadians(float $degrees): float
    {
        return \deg2rad(Numeric::assertFinite($degrees, 'Angle'));
    }

    public static function radiansToDegrees(float $radians): float
    {
        return \rad2deg(Numeric::assertFinite($radians, 'Angle'));
    }
}
