<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Harmony\Wheel;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Harmony\Contract\ClassifyingHueWheel;
use Domm98CZ\Color\Harmony\Internal\Positions;
use Domm98CZ\Color\Harmony\Result\WheelClassification;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Provenance\SourceKind;
use Domm98CZ\Color\Provenance\SourceReference;
use Domm98CZ\Color\Space\ColorSpaces;

/**
 * The HSL (RGB) wheel most web tools use: the HSL hue of the color in sRGB. Primaries are the
 * additive red (0°), green (120°) and blue (240°); secondaries yellow, cyan and magenta.
 */
final readonly class HslHueWheel implements ClassifyingHueWheel
{
    private SpaceHueWheel $wheel;

    public function __construct()
    {
        $this->wheel = new SpaceHueWheel(ColorSpaces::hsl(), new AlgorithmId('hue-wheel-hsl', 1, [
            new SourceReference('CSS Color Module Level 4', SourceKind::Specification, 'W3C Candidate Recommendation Draft, 26 September 2026', '§ 7 HSL', 'https://www.w3.org/TR/css-color-4/'),
        ]));
    }

    public function id(): AlgorithmId
    {
        return $this->wheel->id();
    }

    public function hueOf(Color $color): ?float
    {
        return $this->wheel->hueOf($color);
    }

    public function colorAt(float $angle, Color $template): Color
    {
        return $this->wheel->colorAt($angle, $template);
    }

    public function positions(): array
    {
        return Positions::of(['red', 'green', 'blue'], ['yellow', 'cyan', 'magenta']);
    }

    public function classify(Color $color): WheelClassification
    {
        return Positions::classify($this->positions(), $this->hueOf($color), $this->id());
    }
}
