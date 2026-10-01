<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette\Catalog;

use Domm98CZ\Color\Color;

final readonly class CatalogEntry
{
    /**
     * @param non-empty-string $name
     */
    public function __construct(
        public string $name,
        public Color $color,
    ) {}
}
