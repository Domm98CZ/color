<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Compositing;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Compositing\Algorithm\SourceOver;
use Domm98CZ\Color\Compositing\Contract\CompositeOperator;
use Domm98CZ\Color\Compositing\Result\CompositeResult;

/**
 * An immutable stack of color layers, topmost first:
 *
 *     LayerStack::of($translucentWhite)->over($blue)->composite()->color;
 */
final readonly class LayerStack
{
    /**
     * @param non-empty-list<Color> $layers topmost first
     */
    private function __construct(public array $layers) {}

    public static function of(Color $top, Color ...$below): self
    {
        return new self([$top, ...\array_values($below)]);
    }

    /**
     * A new stack with $backdrop added under the current bottom layer.
     */
    public function over(Color $backdrop): self
    {
        return new self([...$this->layers, $backdrop]);
    }

    /**
     * Composites the layers from the bottom up with $operator (default: source-over in sRGB).
     */
    public function composite(CompositeOperator $operator = new SourceOver()): CompositeResult
    {
        $layers = \array_reverse($this->layers);
        $result = \array_shift($layers)->to($operator->space());

        foreach ($layers as $layer) {
            $result = $operator->composite($layer, $result);
        }

        return new CompositeResult($result, $operator->id(), $this->layers);
    }
}
