<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Harmony\Wheel;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Harmony\Contract\ClassifyingHueWheel;
use Domm98CZ\Color\Harmony\Exception\InvalidHarmonyException;
use Domm98CZ\Color\Harmony\Internal\Positions;
use Domm98CZ\Color\Harmony\Result\WheelClassification;
use Domm98CZ\Color\Math\Angle;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Provenance\SourceKind;
use Domm98CZ\Color\Provenance\SourceReference;
use Domm98CZ\Color\Space\ColorSpaces;

/**
 * The painter's RYB wheel (red, yellow and blue primaries; orange, green and violet secondaries),
 * after N. Gossett and B. Chen, "Paint Inspired Color Mixing and Compositing for Visualization",
 * IEEE InfoVis 2004, § 2.1 and Figure 3.
 *
 * 1. The wheel angle θ gives RYB amounts like the fully saturated RGB wheel gives RGB (θ = 0 red,
 *    60 red+yellow, 120 yellow, 180 yellow+blue, 240 blue, 300 blue+red), as in the paper's Figure 2.
 * 2. RYB is converted to RGB by trilinear interpolation of the RGB colors at the eight corners of
 *    the RYB cube (Figure 3). Linear interpolation is used: it reproduces the paper's worked example
 *    (RYB (1, 0.5, 0.25) → RGB (0.8375, 0.19925, 0.0625)); the "biased" variant's example could not
 *    be reproduced and is not used.
 * 3. The resulting ring of colors defines a reparametrisation of the HSL hue circle: a color's RYB
 *    angle is the θ whose ring color has the color's HSL hue (the ring's hue increases monotonically
 *    with θ, so the inverse is unique; it is found by bisection), and moving a color to θ sets its
 *    HSL hue to that of the ring color, keeping HSL saturation and lightness.
 *
 * The corner for red + blue is (0.5, 0, 0.5): Figure 3 prints "(0.5, 0.5, 0.0)" next to a purple
 * dot, but the worked example in the text only holds with (0.5, 0, 0.5).
 */
final readonly class RybHueWheel implements ClassifyingHueWheel
{
    /** RGB at the RYB cube corners, keyed "ryb" (Gossett and Chen 2004, Figure 3). */
    public const array CORNERS = [
        '000' => [1.0, 1.0, 1.0],
        '100' => [1.0, 0.0, 0.0],
        '010' => [1.0, 1.0, 0.0],
        '001' => [0.163, 0.373, 0.6],
        '110' => [1.0, 0.5, 0.0],
        '101' => [0.5, 0.0, 0.5],
        '011' => [0.0, 0.66, 0.2],
        '111' => [0.2, 0.094, 0.0],
    ];

    private const int BISECTIONS = 64;

    private SpaceHueWheel $hsl;

    public function __construct()
    {
        $this->hsl = new SpaceHueWheel(ColorSpaces::hsl(), $this->id());
    }

    public function id(): AlgorithmId
    {
        return new AlgorithmId('hue-wheel-ryb', 1, [
            new SourceReference('N. Gossett, B. Chen: Paint Inspired Color Mixing and Compositing for Visualization', SourceKind::Paper, 'IEEE Symposium on Information Visualization 2004', '§ 2.1, Figures 2 and 3', 'https://doi.org/10.1109/INFVIS.2004.52'),
        ], 'interpolation=trilinear');
    }

    public function hueOf(Color $color): ?float
    {
        $hue = $this->hsl->hueOf($color);

        return $hue === null ? null : self::angleOfHue($hue);
    }

    public function colorAt(float $angle, Color $template): Color
    {
        if (!\is_finite($angle)) {
            throw new InvalidHarmonyException(\sprintf('A wheel angle must be finite, %s given.', \var_export($angle, true)));
        }

        return $this->hsl->colorAt(self::hueOfAngle(Angle::normalizeDegrees($angle)), $template);
    }

    public function positions(): array
    {
        return Positions::of(['red', 'yellow', 'blue'], ['orange', 'green', 'violet']);
    }

    public function classify(Color $color): WheelClassification
    {
        return Positions::classify($this->positions(), $this->hueOf($color), $this->id());
    }

    /**
     * The RGB color of the wheel at angle θ ∈ [0, 360) (fully saturated RYB).
     *
     * @return array{float, float, float}
     */
    public static function ringColor(float $angle): array
    {
        return self::rgb(...self::ryb($angle));
    }

    /**
     * RYB → RGB by trilinear interpolation of the cube corners.
     *
     * @return array{float, float, float}
     */
    public static function rgb(float $r, float $y, float $b): array
    {
        $result = [];
        for ($channel = 0; $channel < 3; $channel++) {
            $c = static fn(string $corner): float => self::CORNERS[$corner][$channel];
            $x00 = $c('000') + ($c('100') - $c('000')) * $r;
            $x01 = $c('001') + ($c('101') - $c('001')) * $r;
            $x10 = $c('010') + ($c('110') - $c('010')) * $r;
            $x11 = $c('011') + ($c('111') - $c('011')) * $r;
            $x0 = $x00 + ($x10 - $x00) * $y;
            $x1 = $x01 + ($x11 - $x01) * $y;
            $result[] = $x0 + ($x1 - $x0) * $b;
        }

        return [$result[0], $result[1], $result[2]];
    }

    /**
     * @return array{float, float, float}
     */
    private static function ryb(float $angle): array
    {
        $sector = (int) \floor($angle / 60.0);
        $f = $angle / 60.0 - $sector;

        return match ($sector) {
            0 => [1.0, $f, 0.0],
            1 => [1.0 - $f, 1.0, 0.0],
            2 => [0.0, 1.0, $f],
            3 => [0.0, 1.0 - $f, 1.0],
            4 => [$f, 0.0, 1.0],
            default => [1.0, 0.0, 1.0 - $f],
        };
    }

    /**
     * HSL hue of the ring color at θ, unwrapped to [0, 360] so that it increases with θ.
     */
    private static function hueOfAngle(float $angle): float
    {
        [$r, $g, $b] = self::ringColor($angle);
        $max = \max($r, $g, $b);
        $delta = $max - \min($r, $g, $b);

        $hue = match ($max) {
            $r => 60.0 * (($g - $b) / $delta),
            $g => 60.0 * (($b - $r) / $delta) + 120.0,
            default => 60.0 * (($r - $g) / $delta) + 240.0,
        };

        // In the last sector (violet → red) red is the largest channel and blue exceeds green, so the
        // hue formula is negative there: it continues above 300° towards 360°.
        return $hue < 0.0 ? $hue + 360.0 : $hue;
    }

    /**
     * Inverse of {@see hueOfAngle()} by bisection over θ.
     */
    private static function angleOfHue(float $hue): float
    {
        $low = 0.0;
        $high = 360.0;

        for ($i = 0; $i < self::BISECTIONS; $i++) {
            $middle = ($low + $high) / 2.0;
            if (self::hueOfAngle($middle) < $hue) {
                $low = $middle;
            } else {
                $high = $middle;
            }
        }

        return Angle::normalizeDegrees(($low + $high) / 2.0);
    }
}
