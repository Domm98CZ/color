<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Fluent\Result;

use Domm98CZ\Color\Gamut\Result\GamutMappingResult;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * What a fluent chain did: its steps in order and the output conversion that `toColor()`,
 * `toHex()` and `toCss()` apply – the gamut mapping result (whether the color was in gamut, the
 * mapper, ΔEOK to the unmapped color), or null when mapping is switched off.
 */
final readonly class FluentTrace
{
    /**
     * @param non-empty-list<FluentStep> $steps
     */
    public function __construct(
        public array $steps,
        public ColorSpace $outputSpace,
        public ?GamutMappingResult $output,
    ) {}

    /**
     * @return non-empty-list<non-empty-string>
     */
    public function operations(): array
    {
        return \array_map(static fn(FluentStep $step): string => $step->operation, $this->steps);
    }
}
