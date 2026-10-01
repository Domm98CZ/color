<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Difference\Result;

use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Provenance\Diagnostics;

/**
 * The result of a color-difference metric. The value is not rounded and carries no verdict
 * ("imperceptible", "acceptable"…): thresholds are application-specific and are applied by the
 * caller or by a requirement with an explicit source.
 */
final readonly class ColorDifference
{
    public function __construct(
        public float $value,
        public AlgorithmId $metric,
        public Diagnostics $diagnostics,
        public ?DifferenceComponents $components = null,
    ) {}
}
