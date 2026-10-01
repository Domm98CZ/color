<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Operation;

use Domm98CZ\Color\Color;

/**
 * A color stop of a {@see Gradient}: a color at a position in [0, 1] along the gradient.
 */
final readonly class GradientStop
{
    public function __construct(
        public Color $color,
        public float $position,
    ) {}
}
