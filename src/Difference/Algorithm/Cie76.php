<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Difference\Algorithm;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Difference\Contract\ColorDifferenceMetric;
use Domm98CZ\Color\Difference\Internal\LabMetric;
use Domm98CZ\Color\Difference\Internal\Pair;
use Domm98CZ\Color\Difference\Internal\Sources;
use Domm98CZ\Color\Difference\Result\ColorDifference;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Space\Definition\LabSpace;

/**
 * ΔE*ab (CIE 1976): the Euclidean distance in CIELAB. Symmetric.
 */
final readonly class Cie76 implements ColorDifferenceMetric
{
    private LabSpace $lab;

    public function __construct(?LabSpace $lab = null)
    {
        $this->lab = LabMetric::space($lab);
    }

    public function id(): AlgorithmId
    {
        return new AlgorithmId('cie76', 1, [Sources::cie15()], LabMetric::variant($this->lab));
    }

    public function difference(Color $reference, Color $sample): ColorDifference
    {
        $pair = Pair::in($this->lab, $reference, $sample);
        [$l1, $a1, $b1] = $pair->reference;
        [$l2, $a2, $b2] = $pair->sample;

        return new ColorDifference(\sqrt(($l2 - $l1) ** 2 + ($a2 - $a1) ** 2 + ($b2 - $b1) ** 2), $this->id(), $pair->diagnostics);
    }
}
