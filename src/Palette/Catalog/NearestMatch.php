<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette\Catalog;

use Domm98CZ\Color\Difference\Result\ColorDifference;

/**
 * A catalog entry and its difference from the searched color (reference = the searched color).
 */
final readonly class NearestMatch
{
    public function __construct(
        public CatalogEntry $entry,
        public ColorDifference $difference,
    ) {}
}
