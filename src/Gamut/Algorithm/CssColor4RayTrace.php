<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Gamut\Algorithm;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Gamut\Contract\GamutMapper;
use Domm98CZ\Color\Gamut\Exception\UnsupportedGamutException;
use Domm98CZ\Color\Gamut\Internal\Destination;
use Domm98CZ\Color\Gamut\Result\GamutMappingResult;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Definition\LinearRgbSpace;
use Domm98CZ\Color\Space\Definition\RgbSpace;

/**
 * CSS Color 4 "Ray Trace Gamut Mapping" (§ 14.2.5–14.2.6), implemented step by step from the
 * pseudocode: a ray from the achromatic anchor (same OKLCh lightness and hue) to the color is
 * intersected with the [0, 1] cube of the destination's linear-light RGB space; the intersection
 * is projected back onto the constant-lightness, constant-hue line in OKLCh, up to four times;
 * finally the result is clipped to absorb floating-point error.
 *
 * Needs an RGB gamut (the destination itself, or its gamut space such as sRGB for HSL/HWB/HSV).
 */
final readonly class CssColor4RayTrace implements GamutMapper
{
    private const float LOW = 1e-12;
    private const float HIGH = 1.0 - 1e-12;
    private const float PARALLEL = 1e-12;

    public function id(): AlgorithmId
    {
        return new AlgorithmId('css-color-4-ray-trace', 1, [Destination::cssColor4('§ 14.2.5–14.2.6 Ray Trace Gamut Mapping')]);
    }

    /**
     * @throws UnsupportedGamutException when the gamut is not an RGB space
     */
    public function map(Color $color, ColorSpace $destination): GamutMappingResult
    {
        return Destination::result($this->mapColor($color, $destination), $color, $destination, $this->id());
    }

    private function mapColor(Color $origin, ColorSpace $destination): Color
    {
        $gamut = $destination->gamutSpace();

        if ($gamut === null) {
            return $origin->to($destination);
        }

        $linear = match (true) {
            $gamut instanceof RgbSpace => $gamut->base(),
            $gamut instanceof LinearRgbSpace => $gamut,
            default => throw new UnsupportedGamutException(\sprintf('Ray trace gamut mapping needs an RGB gamut; "%s" is not one.', $gamut->id())),
        };

        $oklch = $origin->to(ColorSpaces::oklch());
        [$lightness, , $hue] = $oklch->coordinates();
        $lightness ??= 0.0;

        if ($lightness >= 1.0 || $lightness <= 0.0) {
            return Destination::extreme($origin, $lightness, $destination);
        }

        if ($origin->isInGamut($destination)) {
            return Destination::clip($origin, $destination);
        }

        $alpha = $origin->isAlphaMissing() ? null : $origin->alpha();
        $anchor = self::rgb(Color::of(ColorSpaces::oklch(), [$lightness, 0.0, $hue], $alpha), $linear);
        $current = self::rgb($oklch, $linear);

        $last = $current;

        for ($i = 0; $i < 4; $i++) {
            if ($i > 0) {
                $corrected = Color::of($linear, $current, $alpha)->to(ColorSpaces::oklch())->coordinates();
                $current = self::rgb(Color::of(ColorSpaces::oklch(), [$lightness, $corrected[1], $hue], $alpha), $linear);
            }

            $intersection = self::intersect($anchor, $current);

            if ($intersection === null) {
                $current = $last;
                break;
            }

            if ($i > 0 && self::inCube($current, self::LOW, self::HIGH)) {
                $anchor = $current;
            }

            $current = $intersection;
            $last = $intersection;
        }

        return Destination::clip(Color::of($linear, $current, $alpha), $destination);
    }

    /**
     * @return array{float, float, float}
     */
    private static function rgb(Color $color, ColorSpace $linear): array
    {
        [$r, $g, $b] = $color->to($linear)->coordinates();

        return [$r ?? 0.0, $g ?? 0.0, $b ?? 0.0];
    }

    /**
     * @param array{float, float, float} $rgb
     */
    private static function inCube(array $rgb, float $low, float $high): bool
    {
        foreach ($rgb as $value) {
            if ($value < $low || $value > $high) {
                return false;
            }
        }

        return true;
    }

    /**
     * Intersection of the ray start → end with the [0, 1] cube, favouring the first intersection in
     * the direction of the ray (CSS Color 4 § 14.2.6, "cast a ray").
     *
     * @param array{float, float, float} $start
     * @param array{float, float, float} $end
     *
     * @return array{float, float, float}|null
     */
    private static function intersect(array $start, array $end): ?array
    {
        $tFar = \INF;
        $tNear = -\INF;
        $direction = [];

        foreach ([0, 1, 2] as $i) {
            $a = $start[$i];
            $d = $end[$i] - $a;
            $direction[$i] = $d;

            if (\abs($d) > self::PARALLEL) {
                $t1 = (0.0 - $a) / $d;
                $t2 = (1.0 - $a) / $d;
                $tNear = \max(\min($t1, $t2), $tNear);
                $tFar = \min(\max($t1, $t2), $tFar);
            } elseif ($a < 0.0 || $a > 1.0) {
                return null;
            }
        }

        if ($tNear > $tFar || $tFar < 0.0) {
            return null;
        }

        if ($tNear < 0.0) {
            $tNear = $tFar;
        }

        if (!\is_finite($tNear)) {
            return null;
        }

        return [$start[0] + $direction[0] * $tNear, $start[1] + $direction[1] * $tNear, $start[2] + $direction[2] * $tNear];
    }
}
