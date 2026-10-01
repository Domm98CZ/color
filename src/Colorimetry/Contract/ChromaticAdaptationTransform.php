<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Colorimetry\Contract;

use Domm98CZ\Color\Colorimetry\WhitePoint;
use Domm98CZ\Color\Math\Matrix3;
use Domm98CZ\Color\Provenance\AlgorithmId;

/**
 * A chromatic adaptation transform (CAT): a model of how the appearance of a color is preserved
 * when the adapting white changes. Linear CATs are 3×3 matrices applied to XYZ.
 */
interface ChromaticAdaptationTransform
{
    public function id(): AlgorithmId;

    /**
     * Matrix mapping XYZ relative to $from to XYZ relative to $to (both normalised to Y = 1).
     */
    public function matrix(WhitePoint $from, WhitePoint $to): Matrix3;
}
