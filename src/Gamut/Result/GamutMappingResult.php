<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Gamut\Result;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Difference\Result\ColorDifference;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * The result of gamut mapping: the mapped color (in the destination space), the original, whether
 * the original was already in gamut (then the color is only converted – except that the CSS Color 4
 * mappers return the destination's white or black for OKLCh L ≥ 1 or ≤ 0 before testing the gamut,
 * as the specification orders the steps), the method, and the ΔEOK between the original and the
 * result.
 */
final readonly class GamutMappingResult
{
    public function __construct(
        public Color $color,
        public Color $original,
        public ColorSpace $destination,
        public bool $wasInGamut,
        public AlgorithmId $method,
        public ColorDifference $difference,
    ) {}
}
