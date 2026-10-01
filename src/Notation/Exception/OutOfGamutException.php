<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Notation\Exception;

use Domm98CZ\Color\Exception\DomainException;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * The color cannot be written in a notation that is limited to a gamut (HEX is limited to sRGB)
 * without changing it. The library never clips silently: map the color into the gamut explicitly,
 * or use a notation that keeps out-of-gamut values (CSS `color()`, JSON).
 */
final class OutOfGamutException extends DomainException
{
    /**
     * @param list<float|null> $coordinates the color in its own space
     */
    public function __construct(
        private readonly ColorSpace $space,
        private readonly array $coordinates,
        private readonly ColorSpace $targetSpace,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function getSpace(): ColorSpace
    {
        return $this->space;
    }

    /**
     * @return list<float|null>
     */
    public function getCoordinates(): array
    {
        return $this->coordinates;
    }

    public function getTargetSpace(): ColorSpace
    {
        return $this->targetSpace;
    }
}
