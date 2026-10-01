<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Harmony\Result;

use Domm98CZ\Color\Color;

/**
 * One color of a harmony: its offset from the base, its angle on the wheel (null when the base has
 * no hue) and whether it is inside the checked gamut. Colors are never clipped.
 */
final readonly class HarmonyMember
{
    public function __construct(
        public Color $color,
        public float $offset,
        public ?float $angle,
        public bool $inGamut,
    ) {}
}
