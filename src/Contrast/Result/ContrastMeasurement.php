<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Contrast\Result;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Compositing\Result\CompositeResult;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Provenance\Diagnostics;

/**
 * A contrast value with everything needed to reproduce it. The value is never rounded
 * (WCAG: 4.499 is not 4.5).
 *
 * `foreground` and `background` are the opaque colors that were actually compared: a translucent
 * foreground composited over the background, a layer stack composited to one color.
 */
final readonly class ContrastMeasurement
{
    public function __construct(
        public float $value,
        public AlgorithmId $algorithm,
        public Color $foreground,
        public Color $background,
        public ?CompositeResult $backgroundComposition,
        public ?CompositeResult $foregroundComposition,
        public Diagnostics $diagnostics,
        public bool $experimental = false,
    ) {}
}
