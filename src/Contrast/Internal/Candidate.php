<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Contrast\Internal;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Contrast\Result\ContrastMeasurement;
use Domm98CZ\Color\Gamut\Result\GamutMappingResult;

/**
 * @internal one evaluated candidate of the contrast lightness search: the fitted color, how it was
 * mapped, its measurement and the model lightness it was built with
 */
final readonly class Candidate
{
    public function __construct(
        public Color $color,
        public GamutMappingResult $mapping,
        public ContrastMeasurement $measurement,
        public float $lightness,
    ) {}

    public function passes(float $target): bool
    {
        return $this->measurement->value >= $target;
    }
}
