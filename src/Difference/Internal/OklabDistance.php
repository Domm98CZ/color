<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Difference\Internal;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Difference\Result\ColorDifference;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Space\ColorSpaces;

/**
 * @internal √(ΔL² + (s·Δa)² + (s·Δb)²) in OKLab (CSS Color 4 § 20.3–20.4)
 */
final class OklabDistance
{
    private function __construct() {}

    public static function of(Color $reference, Color $sample, float $abScale, AlgorithmId $metric): ColorDifference
    {
        $pair = Pair::in(ColorSpaces::oklab(), $reference, $sample);
        [$l1, $a1, $b1] = $pair->reference;
        [$l2, $a2, $b2] = $pair->sample;

        return new ColorDifference(
            \sqrt(($l2 - $l1) ** 2 + ($abScale * ($a2 - $a1)) ** 2 + ($abScale * ($b2 - $b1)) ** 2),
            $metric,
            $pair->diagnostics,
        );
    }
}
