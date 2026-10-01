<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Support;

use Domm98CZ\Color\Math\Tolerance;

/**
 * Named, justified tolerances for tests.
 *
 * Rule: no test uses a bare numeric tolerance. Add a constant here with its rationale instead.
 */
final class Tolerances
{
    /**
     * Linear 3×3 algebra (products, inverses, round trips) on well-conditioned color matrices.
     * float64 ε ≈ 2.2e-16; an inverse needs ~30 flops and the condition number of color transform
     * matrices is O(10), so the expected error is ≲ 1e-14. 1e-12 leaves a 100× margin.
     */
    public const float LINEAR_ALGEBRA_ABSOLUTE = 1e-12;

    /**
     * Numeric::cbrt() after one Newton step is within ~1 ulp of the true root; allow 4 ε relative.
     */
    public const float CBRT_RELATIVE = 4 * \PHP_FLOAT_EPSILON;

    /**
     * Angle arithmetic in degrees on values up to a few thousand degrees: fmod() is exact, the only
     * rounding comes from one addition/subtraction of 360 (ulp(360) ≈ 5.7e-14). 1e-9° is far below
     * any perceptible hue difference and far above the arithmetic error.
     */
    public const float ANGLE_DEGREES = 1e-9;

    /**
     * Round trip sRGB → any built-in space → sRGB, in sRGB units (0–1). The longest paths
     * (e.g. sRGB → XYZ → Bradford → Lab → LCh and back) take ~200 flops including pow/cbrt/atan2,
     * each within a few ulp: expected error ≲ 1e-13. 1e-10 leaves a margin of three orders of
     * magnitude and is still 5 orders below an 8-bit quantisation step.
     */
    public const float CONVERSION_ROUND_TRIP_SRGB = 1e-10;

    /**
     * Round trip through a cylindrical space in which the hue became powerless: CSS Color 4 § 4.4.1
     * sets the colorfulness (≤ ε) to zero, which changes the color by at most ε of that space.
     * The largest effect is LCh (ε = 0.0015 in Lab units ≈ 2e-5 in sRGB); 1e-4 bounds all spaces.
     */
    public const float POWERLESS_ROUND_TRIP_SRGB = 1e-4;

    /**
     * One just noticeable difference in OKLCh / ΔEOK (CSS Color 4 § 14.2.1). The CSS Color 4 gamut
     * mappers accept a clipped color when it is within one JND of the chroma-reduced color, so the
     * lightness of a mapped color may differ from the original by up to this amount, and its hue
     * by up to JND / chroma radians.
     */
    public const float OKLCH_JND = 0.02;

    /**
     * One 8-bit step of an sRGB channel (1/255). Browsers serialize legacy sRGB colors at 8 bits, so
     * a web-platform-tests specified value such as `color-mix(in hwb, rgb(153, 102, 77), …)` has
     * inputs rounded by up to half a step; mixing in HSL/HWB can enlarge that (measured maximum
     * 0.75 step, 2026-09-28).
     */
    public const float SRGB_EIGHT_BIT_STEP = 1 / 255;

    /**
     * toCss() → parse() with the default CssFormat: numbers are rounded to 6 decimal places
     * (error ≤ 5e-7 in the written unit) and an sRGB channel within the documented 1e-6 gamut
     * tolerance is written clamped.
     */
    public const float CSS_ROUND_TRIP = 1.1e-6;

    /**
     * Two results that a test requires to be different (e.g. Lab D50 vs D65, CIE94 in both orders)
     * must differ by more than this; far above rounding noise, far below the differences tested.
     */
    public const float DISTINCT = 1e-3;

    /**
     * The two branches of a piecewise function meet only up to this at the published breakpoint:
     * sRGB 0.04045 (the exact intersection is 0.0392857…/0.04045 differs by rounding; measured
     * gap 2.3e-9) and CIELAB ε, κ (measured gap 9e-10).
     */
    public const float PIECEWISE_JOIN = 1e-8;

    private function __construct() {}

    /**
     * Half a unit in the last printed digit of a reference value given with $decimals decimal
     * places (the rounding error of the printed value).
     */
    public static function printedDecimals(int $decimals): float
    {
        return 0.5 * 10 ** -$decimals;
    }

    public static function linearAlgebra(): Tolerance
    {
        return Tolerance::absolute(self::LINEAR_ALGEBRA_ABSOLUTE);
    }
}
