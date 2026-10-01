<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Difference\Algorithm;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Difference\Contract\ColorDifferenceMetric;
use Domm98CZ\Color\Difference\Internal\OklabDistance;
use Domm98CZ\Color\Difference\Internal\Sources;
use Domm98CZ\Color\Difference\Result\ColorDifference;
use Domm98CZ\Color\Provenance\AlgorithmId;

/**
 * ΔEOK (CSS Color 4 § 20.3): the Euclidean distance in OKLab. Symmetric.
 * The values are on the OKLab scale (L 0–1), about 1/100 of ΔE*ab for similar colors.
 */
final readonly class DeltaEOk implements ColorDifferenceMetric
{
    public function id(): AlgorithmId
    {
        return new AlgorithmId('deltae-ok', 1, [Sources::cssColor4('§ 20.3 ΔEOK (not normative)')]);
    }

    public function difference(Color $reference, Color $sample): ColorDifference
    {
        return OklabDistance::of($reference, $sample, 1.0, $this->id());
    }
}
