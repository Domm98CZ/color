<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Fluent\Result;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Provenance\Diagnostics;

/**
 * One step of a fluent chain: the operation, every parameter that influenced it (including the
 * model or space taken from the configuration), the algorithm where the step used one (a hue
 * wheel), the resulting color and the diagnostics of the service.
 */
final readonly class FluentStep
{
    public Diagnostics $diagnostics;

    /**
     * @param non-empty-string $operation e.g. 'lighten', 'mix'
     * @param array<string, scalar|null> $parameters e.g. ['by' => 0.08, 'model' => 'oklch-l']
     */
    public function __construct(
        public string $operation,
        public array $parameters,
        public Color $result,
        public ?AlgorithmId $algorithm = null,
        ?Diagnostics $diagnostics = null,
    ) {
        $this->diagnostics = $diagnostics ?? Diagnostics::none();
    }
}
