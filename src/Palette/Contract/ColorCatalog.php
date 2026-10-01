<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette\Contract;

use Domm98CZ\Color\Palette\Catalog\CatalogEntry;

/**
 * A named set of reference colors (CSS named colors, a brand library, licensed swatch data the
 * user provides). The library ships only data it may distribute.
 */
interface ColorCatalog
{
    /**
     * @return non-empty-string
     */
    public function id(): string;

    /**
     * @return list<CatalogEntry>
     */
    public function entries(): array;
}
