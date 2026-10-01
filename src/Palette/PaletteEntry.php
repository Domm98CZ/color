<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette;

use Domm98CZ\Color\Color;

/**
 * A color in a palette, optionally named (e.g. "primary", "500").
 */
final readonly class PaletteEntry
{
    /**
     * @param non-empty-string|null $name
     */
    public function __construct(
        public Color $color,
        public ?string $name = null,
    ) {}
}
