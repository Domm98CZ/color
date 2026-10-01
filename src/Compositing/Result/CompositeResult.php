<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Compositing\Result;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Provenance\AlgorithmId;

/**
 * The result of compositing a stack of layers: the resulting color (in the operator's space),
 * the operator used and the layers, topmost first.
 */
final readonly class CompositeResult
{
    /**
     * @param non-empty-list<Color> $layers topmost first
     */
    public function __construct(
        public Color $color,
        public AlgorithmId $operator,
        public array $layers,
    ) {}

    public function isOpaque(): bool
    {
        return $this->color->alpha() === 1.0;
    }
}
