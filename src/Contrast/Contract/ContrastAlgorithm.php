<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Contrast\Contract;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Compositing\LayerStack;
use Domm98CZ\Color\Contrast\Result\ContrastMeasurement;
use Domm98CZ\Color\Provenance\AlgorithmId;

/**
 * A method of computing contrast between a foreground and its background. It returns a number and
 * knows nothing about conformance levels (AA/AAA); thresholds are applied by the caller.
 */
interface ContrastAlgorithm
{
    public function id(): AlgorithmId;

    /**
     * @param Color|LayerStack $background an opaque color, or layers that composite to an opaque color
     */
    public function measure(Color $foreground, Color|LayerStack $background): ContrastMeasurement;
}
