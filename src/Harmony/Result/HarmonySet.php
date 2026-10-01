<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Harmony\Result;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Harmony\HarmonyScheme;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Provenance\Diagnostics;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * The colors of a harmony scheme on a wheel, in the order of the scheme's offsets.
 */
final readonly class HarmonySet
{
    /**
     * @param non-empty-list<HarmonyMember> $members
     */
    public function __construct(
        public Color $base,
        public HarmonyScheme $scheme,
        public AlgorithmId $wheel,
        public ColorSpace $gamut,
        public array $members,
        public Diagnostics $diagnostics,
    ) {}

    /**
     * @return non-empty-list<Color>
     */
    public function colors(): array
    {
        return \array_map(static fn(HarmonyMember $member): Color => $member->color, $this->members);
    }

    public function allInGamut(): bool
    {
        foreach ($this->members as $member) {
            if (!$member->inGamut) {
                return false;
            }
        }

        return true;
    }
}
