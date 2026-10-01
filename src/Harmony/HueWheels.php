<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Harmony;

use Domm98CZ\Color\Harmony\Contract\HueWheel;
use Domm98CZ\Color\Harmony\Wheel\HslHueWheel;
use Domm98CZ\Color\Harmony\Wheel\RybHueWheel;
use Domm98CZ\Color\Harmony\Wheel\SpaceHueWheel;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Provenance\SourceKind;
use Domm98CZ\Color\Provenance\SourceReference;
use Domm98CZ\Color\Space\ColorSpaces;

/**
 * Factories of the built-in color wheels. The wheel is always chosen explicitly:
 *
 * - {@see oklch()} – perceptual hue (OKLCh); keeps OKLab lightness and chroma when rotating;
 * - {@see cieLch()} – CIE LCh hue (D50, CSS `lch()`);
 * - {@see hsl()} – the RGB/HSL wheel of most web tools, with additive primaries;
 * - {@see ryb()} – the painter's wheel with red, yellow and blue primaries.
 */
final class HueWheels
{
    private function __construct() {}

    public static function oklch(): HueWheel
    {
        return new SpaceHueWheel(ColorSpaces::oklch(), new AlgorithmId('hue-wheel-oklch', 1, [
            new SourceReference('CSS Color Module Level 4', SourceKind::Specification, 'W3C Candidate Recommendation Draft, 26 September 2026', '§ 9.2 OkLCh', 'https://www.w3.org/TR/css-color-4/'),
        ]));
    }

    public static function cieLch(): HueWheel
    {
        return new SpaceHueWheel(ColorSpaces::lch(), new AlgorithmId('hue-wheel-cielch', 1, [
            new SourceReference('CSS Color Module Level 4', SourceKind::Specification, 'W3C Candidate Recommendation Draft, 26 September 2026', '§ 9.1 CIE LCH', 'https://www.w3.org/TR/css-color-4/'),
        ], 'white=D50'));
    }

    public static function hsl(): HslHueWheel
    {
        return new HslHueWheel();
    }

    public static function ryb(): RybHueWheel
    {
        return new RybHueWheel();
    }
}
