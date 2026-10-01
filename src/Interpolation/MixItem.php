<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Interpolation;

use Domm98CZ\Color\Interpolation\Exception\InvalidInterpolationException;
use Domm98CZ\Color\Space\ColorValue;

/**
 * One argument of a mix: a color and an optional percentage, written as a fraction in [0, 1]
 * (CSS `30%` = 0.3). A null percentage is filled in by the normalisation ({@see ColorMix}).
 */
final readonly class MixItem
{
    /**
     * @throws InvalidInterpolationException when the percentage is not in [0, 1]
     */
    public function __construct(
        public ColorValue $color,
        public ?float $percentage = null,
    ) {
        if ($percentage !== null && !($percentage >= 0.0 && $percentage <= 1.0)) {
            throw new InvalidInterpolationException(\sprintf('A mix percentage must be in [0, 1] (0%% to 100%%), %s given.', \var_export($percentage, true)));
        }
    }
}
