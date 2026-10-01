<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Harmony\Result;

use Domm98CZ\Color\Harmony\ColorOrder;
use Domm98CZ\Color\Harmony\WheelPosition;
use Domm98CZ\Color\Provenance\AlgorithmId;

/**
 * The nearest named position of a color on a wheel. For an achromatic color the order is
 * {@see ColorOrder::Achromatic} and the other fields are null.
 */
final readonly class WheelClassification
{
    /**
     * @param float|null $hue the color's angle on the wheel
     * @param float|null $distance angular distance from the position, in [0, 15]
     */
    public function __construct(
        public ColorOrder $order,
        public ?WheelPosition $position,
        public ?float $hue,
        public ?float $distance,
        public AlgorithmId $wheel,
    ) {}
}
