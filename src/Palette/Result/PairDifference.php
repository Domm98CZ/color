<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette\Result;

use Domm98CZ\Color\Difference\Result\ColorDifference;

/**
 * The difference between two palette entries (0-based positions; the first is the reference).
 */
final readonly class PairDifference
{
    public function __construct(
        public int $first,
        public int $second,
        public ColorDifference $difference,
    ) {}
}
