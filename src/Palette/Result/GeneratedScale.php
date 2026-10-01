<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette\Result;

use Domm98CZ\Color\Gamut\Result\GamutMappingResult;
use Domm98CZ\Color\Palette\Palette;

/**
 * A generated scale: the palette and, when a gamut mapper was used, the mapping of every step.
 */
final readonly class GeneratedScale
{
    /**
     * @param list<GamutMappingResult|null> $mapping one per step; null when no mapper was used
     */
    public function __construct(
        public Palette $palette,
        public array $mapping,
    ) {}

    /**
     * Positions of the steps that were outside the gamut and changed by the mapper.
     *
     * @return list<int>
     */
    public function mappedSteps(): array
    {
        $mapped = [];
        foreach ($this->mapping as $index => $result) {
            if ($result !== null && !$result->wasInGamut) {
                $mapped[] = $index;
            }
        }

        return $mapped;
    }
}
