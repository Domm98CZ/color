<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Operation\Result;

use Domm98CZ\Color\Color;

/**
 * A color quantised to integer codes of a fixed bit depth.
 */
final readonly class QuantizedColor
{
    /**
     * @param list<int> $codes one code per channel, 0 … 2^bits − 1
     * @param int $alphaCode the alpha as a code, 0 … 2^bits − 1
     * @param Color $color the quantised color (code / (2^bits − 1)), in the quantisation space
     */
    public function __construct(
        public array $codes,
        public int $alphaCode,
        public int $bits,
        public Color $color,
    ) {}

    public function maxCode(): int
    {
        return (1 << $this->bits) - 1;
    }
}
