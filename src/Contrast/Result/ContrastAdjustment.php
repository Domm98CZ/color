<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Contrast\Result;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Gamut\Result\GamutMappingResult;
use Domm98CZ\Color\Operation\LightnessModel;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Provenance\Diagnostics;

/**
 * A color adjusted to a contrast target: the adjusted color (in sRGB, quantised, so `toHex()`
 * writes exactly what was measured), the input, the measurement of the adjusted color against
 * the fixed one, whether the target was reached, and how the color was changed.
 *
 * `algorithm` identifies the search; the contrast model is `contrast->algorithm`, the gamut
 * mapper `mapping->method`. When `reachable` is false, `color` is the best candidate found and
 * `diagnostics` carries a `contrast-target-unreachable` warning.
 */
final readonly class ContrastAdjustment
{
    /**
     * @param float $lightnessChange the lightness set on the candidate minus the input's, in the units of the model
     */
    public function __construct(
        public Color $color,
        public Color $original,
        public ContrastMeasurement $contrast,
        public float $target,
        public bool $reachable,
        public LightnessModel $lightness,
        public float $lightnessChange,
        public GamutMappingResult $mapping,
        public AlgorithmId $algorithm,
        public Diagnostics $diagnostics,
    ) {}
}
