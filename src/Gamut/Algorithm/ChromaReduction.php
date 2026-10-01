<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Gamut\Algorithm;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Gamut\Contract\GamutMapper;
use Domm98CZ\Color\Gamut\Internal\Destination;
use Domm98CZ\Color\Gamut\Result\GamutMappingResult;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Plain chroma reduction (CSS Color 4 § 14.1.3): OKLCh lightness and hue are kept, the chroma is
 * reduced by binary search (to 0.0001) until the color is in gamut. Unlike the CSS Color 4 mapper
 * it has no local-MINDE step, so very light colors on a shallow gamut surface may lose more chroma
 * (§ 14.1.4). The result is always strictly the in-gamut side of the search.
 */
final readonly class ChromaReduction implements GamutMapper
{
    public const float EPSILON = 0.0001;

    public function id(): AlgorithmId
    {
        return new AlgorithmId('oklch-chroma-reduction', 1, [Destination::cssColor4('§ 14.1.3 Chroma Reduction')]);
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

        $min = 0.0;
        $max = $current->coordinates()[1] ?? 0.0;

        while ($max - $min > self::EPSILON) {
            $chroma = ($min + $max) / 2.0;

            if ($current->withCoordinate(1, $chroma)->isInGamut($destination)) {
                $min = $chroma;
            } else {
                $max = $chroma;
            }
        }

        // The neutral color at 0 < L < 1 is inside every RGB gamut, so $min is in gamut; clipping
        // only removes the ≤ 1e-6 overshoot that the gamut tolerance allows.
        return Destination::clip($current->withCoordinate(1, $min), $destination);
    }
}
