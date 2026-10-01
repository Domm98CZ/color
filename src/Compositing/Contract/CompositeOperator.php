<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Compositing\Contract;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * A compositing operator: combines a source color with its backdrop (W3C Compositing and
 * Blending Level 1). The result is in {@see space()}.
 */
interface CompositeOperator
{
    public function id(): AlgorithmId;

    /**
     * The space in which the operator works and in which it returns colors.
     */
    public function space(): ColorSpace;

    public function composite(Color $source, Color $backdrop): Color;
}
