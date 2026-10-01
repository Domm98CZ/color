<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Gamut\Algorithm;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Difference\DeltaE;
use Domm98CZ\Color\Gamut\Contract\GamutMapper;
use Domm98CZ\Color\Gamut\Internal\Destination;
use Domm98CZ\Color\Gamut\Result\GamutMappingResult;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * CSS Color 4 "Binary Search Gamut Mapping with Local MINDE" (§ 14.2.1–14.2.2), implemented step by
 * step from the pseudocode: constant-lightness, constant-hue chroma reduction in OKLCh; at each
 * step the ΔEOK between the current color and its clipped version is compared with the just
 * noticeable difference JND = 0.02; the search stops at ε = 0.0001.
 *
 * Relative colorimetric: colors in gamut are only converted. Lightness ≥ 1 gives white, ≤ 0 black.
 */
final readonly class CssColor4BinarySearch implements GamutMapper
{
    public const float JND = 0.02;
    public const float EPSILON = 0.0001;

    public function id(): AlgorithmId
    {
        return new AlgorithmId('css-color-4-binary-search-local-minde', 1, [Destination::cssColor4('§ 14.2.1–14.2.2 Binary Search Gamut Mapping with Local MINDE')]);
    }

    public function map(Color $color, ColorSpace $destination): GamutMappingResult
    {
        return Destination::result($this->mapColor($color, $destination), $color, $destination, $this->id());
    }

    private function mapColor(Color $origin, ColorSpace $destination): Color
    {
        if ($destination->gamutSpace() === null) {
            return $origin->to($destination);
        }

        $current = $origin->to(ColorSpaces::oklch());
        $lightness = $current->coordinates()[0] ?? 0.0;

        if ($lightness >= 1.0 || $lightness <= 0.0) {
            return Destination::extreme($origin, $lightness, $destination);
        }

        if ($origin->isInGamut($destination)) {
            return Destination::clip($origin, $destination);
        }

        $clipped = Destination::clip($current, $destination);
        if (self::delta($clipped, $current) < self::JND) {
            return $clipped;
        }

        $min = 0.0;
        $max = $current->coordinates()[1] ?? 0.0;
        $minInGamut = true;

        while ($max - $min > self::EPSILON) {
            $chroma = ($min + $max) / 2.0;
            $current = $current->withCoordinate(1, $chroma);

            if ($minInGamut && $current->isInGamut($destination)) {
                $min = $chroma;
                continue;
            }

            $clipped = Destination::clip($current, $destination);
            $e = self::delta($clipped, $current);

            if ($e < self::JND) {
                if (self::JND - $e < self::EPSILON) {
                    return $clipped;
                }

                $minInGamut = false;
                $min = $chroma;
            } else {
                $max = $chroma;
            }
        }

        return $clipped;
    }

    private static function delta(Color $one, Color $two): float
    {
        return DeltaE::ok()->difference($one, $two)->value;
    }
}
