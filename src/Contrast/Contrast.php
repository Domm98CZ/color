<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Contrast;

use Domm98CZ\Color\Contrast\Algorithm\Wcag2ContrastRatio;
use Domm98CZ\Color\Contrast\Algorithm\Wcag2LuminanceThreshold;

/**
 * Factories of the built-in contrast algorithms. The algorithm is always chosen explicitly.
 *
 *     Contrast::wcag2()->measure($text, $background)->value;   // 4.478… (never rounded)
 */
final class Contrast
{
    private function __construct() {}

    /**
     * WCAG 2 contrast ratio; the luminance threshold 0.04045 of WCAG 2.1/2.2 by default,
     * Wcag2LuminanceThreshold::Wcag20 for the 0.03928 of WCAG 2.0.
     */
    public static function wcag2(Wcag2LuminanceThreshold $threshold = Wcag2LuminanceThreshold::Current): Wcag2ContrastRatio
    {
        return new Wcag2ContrastRatio($threshold);
    }
}
