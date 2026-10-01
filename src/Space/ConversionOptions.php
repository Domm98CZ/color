<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space;

use Domm98CZ\Color\Colorimetry\Algorithm\Bradford;
use Domm98CZ\Color\Colorimetry\Contract\ChromaticAdaptationTransform;

/**
 * Choices that affect a conversion between color spaces.
 *
 * The default chromatic adaptation is linear Bradford, as in CSS Color 4 and ICC. Changing the default
 * is a breaking change.
 */
final readonly class ConversionOptions
{
    public function __construct(
        public ChromaticAdaptationTransform $adaptation = new Bradford(),
    ) {}
}
