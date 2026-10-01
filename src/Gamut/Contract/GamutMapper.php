<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Gamut\Contract;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Gamut\Result\GamutMappingResult;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Brings a color into the gamut of a destination space. Mapping is always explicit: nothing else in
 * the library clips or maps silently.
 */
interface GamutMapper
{
    public function id(): AlgorithmId;

    /**
     * The result color is in $destination. A destination without a gamut boundary (XYZ, Lab, OKLab…)
     * only converts.
     */
    public function map(Color $color, ColorSpace $destination): GamutMappingResult;
}
