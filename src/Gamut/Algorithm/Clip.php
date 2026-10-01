<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Gamut\Algorithm;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Gamut\Contract\GamutMapper;
use Domm98CZ\Color\Gamut\Internal\Destination;
use Domm98CZ\Color\Gamut\Result\GamutMappingResult;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Clipping (CSS Color 4 § 14.1.1): each component of the destination's gamut space (gamma-encoded
 * for RGB spaces) is clamped to its reference range. Fast, but it shifts hue for colors far out of
 * gamut; prefer the CSS Color 4 mappers.
 */
final readonly class Clip implements GamutMapper
{
    public function id(): AlgorithmId
    {
        return new AlgorithmId('clip', 1, [Destination::cssColor4('§ 14.1.1 Clipping')]);
    }

    public function map(Color $color, ColorSpace $destination): GamutMappingResult
    {
        return Destination::result(Destination::clip($color, $destination), $color, $destination, $this->id());
    }
}
