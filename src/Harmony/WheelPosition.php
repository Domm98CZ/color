<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Harmony;

/**
 * One of the twelve named positions of a wheel with primaries.
 */
final readonly class WheelPosition
{
    /**
     * @param non-empty-string $name
     */
    public function __construct(
        public string $name,
        public float $angle,
        public ColorOrder $order,
    ) {}
}
