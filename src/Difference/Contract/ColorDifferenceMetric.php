<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Difference\Contract;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Difference\Result\ColorDifference;
use Domm98CZ\Color\Provenance\AlgorithmId;

/**
 * A color-difference metric (ΔE). Implementations state their identity, parametrisation and
 * sources in {@see id()}; the result carries the same id.
 *
 * A metric may be asymmetric: the first argument is the reference (standard) and the second the
 * sample. The built-in metrics are symmetric.
 */
interface ColorDifferenceMetric
{
    public function id(): AlgorithmId;

    public function difference(Color $reference, Color $sample): ColorDifference;
}
