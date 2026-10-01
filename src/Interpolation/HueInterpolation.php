<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Interpolation;

/**
 * How hue angles are interpolated in a space with a hue channel
 * (CSS Color 4 § 13.5, CRD 2026-09-26). The CSS default is {@see Shorter}.
 *
 * The value is the CSS keyword (`<hue-interpolation-method>` without the trailing `hue`).
 */
enum HueInterpolation: string
{
    /** The shorter arc: θ₂ − θ₁ ∈ [−180, 180]. */
    case Shorter = 'shorter';

    /** The longer arc: θ₂ − θ₁ ∈ (−360, −180] ∪ [180, 360). Equal hues go once around the circle. */
    case Longer = 'longer';

    /** Always increasing: θ₂ − θ₁ ∈ [0, 360). */
    case Increasing = 'increasing';

    /** Always decreasing: θ₂ − θ₁ ∈ (−360, 0]. */
    case Decreasing = 'decreasing';

    /**
     * Fixes up two hues in [0, 360) so that linear interpolation between them follows this method.
     * One of them may be raised by 360; the pseudocode of § 13.5.1–13.5.4 is followed literally.
     *
     * @return array{float, float}
     */
    public function fixup(float $first, float $second): array
    {
        $difference = $second - $first;

        switch ($this) {
            case self::Shorter:
                if ($difference > 180.0) {
                    $first += 360.0;
                } elseif ($difference < -180.0) {
                    $second += 360.0;
                }
                break;
            case self::Longer:
                if ($difference > 0.0 && $difference < 180.0) {
                    $first += 360.0;
                } elseif ($difference > -180.0 && $difference <= 0.0) {
                    $second += 360.0;
                }
                break;
            case self::Increasing:
                if ($second < $first) {
                    $second += 360.0;
                }
                break;
            case self::Decreasing:
                if ($first < $second) {
                    $first += 360.0;
                }
                break;
        }

        return [$first, $second];
    }
}
