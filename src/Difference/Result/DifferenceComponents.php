<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Difference\Result;

/**
 * Lightness, chroma and hue differences (sample − reference) as the metric defines them, before
 * weighting: ΔL*, ΔC*ab, ΔH*ab for CIE94 and CMC; ΔL′, ΔC′, ΔH′ for CIEDE2000.
 */
final readonly class DifferenceComponents
{
    public function __construct(
        public float $deltaL,
        public float $deltaC,
        public float $deltaH,
    ) {}
}
