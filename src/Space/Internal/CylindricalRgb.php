<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Internal;

use Domm98CZ\Color\Math\Angle;

/**
 * @internal HSL / HWB helper functions transcribed from CSS Color 4 (CRD 2026-09-26), §7.1, §7.2, §8.1, §8.2.
 *
 * The original sample code is Copyright © World Wide Web Consortium and is used under the W3C Software
 * and Document License (https://www.w3.org/copyright/software-license-2023/).
 *
 * All RGB values are in [0, 1] for in-gamut colors; saturation, lightness, whiteness and blackness
 * are on the 0–100 scale used by CSS.
 */
final class CylindricalRgb
{
    private function __construct() {}

    /**
     * CSS Color 4 hslToRgb(): hue in degrees, saturation and lightness 0–100.
     *
     * @return array{float, float, float}
     */
    public static function hslToRgb(float $hue, float $saturation, float $lightness): array
    {
        $hue = Angle::normalizeDegrees($hue);
        $saturation /= 100.0;
        $lightness /= 100.0;
        $a = $saturation * \min($lightness, 1.0 - $lightness);

        $f = static function (float $n) use ($hue, $lightness, $a): float {
            $k = \fmod($n + $hue / 30.0, 12.0);

            return $lightness - $a * \max(-1.0, \min($k - 3.0, 9.0 - $k, 1.0));
        };

        return [$f(0.0), $f(8.0), $f(4.0)];
    }

    /**
     * CSS Color 4 rgbToHsl(), including the rotation of negative saturation (w3c/csswg-drafts#9222).
     * The returned hue is NAN when the sample code would return NaN; callers apply the powerless rule.
     *
     * @return array{float, float, float} hue (degrees or NAN), saturation 0–100, lightness 0–100
     */
    public static function rgbToHsl(float $red, float $green, float $blue): array
    {
        $max = \max($red, $green, $blue);
        $min = \min($red, $green, $blue);
        $hue = \NAN;
        $saturation = 0.0;
        $lightness = ($min + $max) / 2.0;
        $d = $max - $min;

        if ($d !== 0.0) {
            $saturation = ($lightness === 0.0 || $lightness === 1.0)
                ? 0.0
                : ($max - $lightness) / \min($lightness, 1.0 - $lightness);
            $hue = self::hueFromMax($red, $green, $blue, $max, $d);
        }

        if ($saturation < 0.0) {
            $hue += 180.0;
            $saturation = \abs($saturation);
        }

        if ($hue >= 360.0) {
            $hue -= 360.0;
        }

        return [$hue, $saturation * 100.0, $lightness * 100.0];
    }

    /**
     * CSS Color 4 hwbToRgb(): whiteness and blackness 0–100.
     *
     * @return array{float, float, float}
     */
    public static function hwbToRgb(float $hue, float $whiteness, float $blackness): array
    {
        $whiteness /= 100.0;
        $blackness /= 100.0;

        if ($whiteness + $blackness >= 1.0) {
            $gray = $whiteness / ($whiteness + $blackness);

            return [$gray, $gray, $gray];
        }

        $rgb = self::hslToRgb($hue, 100.0, 50.0);
        $scale = 1.0 - $whiteness - $blackness;

        return [$rgb[0] * $scale + $whiteness, $rgb[1] * $scale + $whiteness, $rgb[2] * $scale + $whiteness];
    }

    /**
     * CSS Color 4 rgbToHue(): hue in degrees, or NAN when max = min.
     */
    public static function rgbToHue(float $red, float $green, float $blue): float
    {
        $max = \max($red, $green, $blue);
        $d = $max - \min($red, $green, $blue);

        if ($d === 0.0) {
            return \NAN;
        }

        $hue = self::hueFromMax($red, $green, $blue, $max, $d);

        return $hue >= 360.0 ? $hue - 360.0 : $hue;
    }

    private static function hueFromMax(float $red, float $green, float $blue, float $max, float $d): float
    {
        $hue = match (true) {
            $max === $red => ($green - $blue) / $d + ($green < $blue ? 6.0 : 0.0),
            $max === $green => ($blue - $red) / $d + 2.0,
            default => ($red - $green) / $d + 4.0,
        };

        return $hue * 60.0;
    }
}
