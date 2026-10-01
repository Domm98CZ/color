<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Math;

use Domm98CZ\Color\Math\Exception\InvalidToleranceException;

/**
 * Numeric tolerance combining an absolute and a relative bound.
 *
 * Two values a, b are close when |a − b| ≤ max(absolute, relative · max(|a|, |b|)).
 * Non-finite values are close only when they are identical infinities; NaN is never close.
 */
final readonly class Tolerance
{
    private function __construct(
        public float $absolute,
        public float $relative,
    ) {
        if (!\is_finite($absolute) || $absolute < 0.0) {
            throw InvalidToleranceException::for('absolute', $absolute);
        }

        if (!\is_finite($relative) || $relative < 0.0) {
            throw InvalidToleranceException::for('relative', $relative);
        }
    }

    public static function absolute(float $absolute): self
    {
        return new self($absolute, 0.0);
    }

    public static function relative(float $relative): self
    {
        return new self(0.0, $relative);
    }

    public static function of(float $absolute, float $relative): self
    {
        return new self($absolute, $relative);
    }

    /**
     * A zero tolerance: only identical finite values are close.
     */
    public static function exact(): self
    {
        return new self(0.0, 0.0);
    }

    public function isClose(float $a, float $b): bool
    {
        if (!\is_finite($a) || !\is_finite($b)) {
            return $a === $b;
        }

        $bound = \max($this->absolute, $this->relative * \max(\abs($a), \abs($b)));

        return \abs($a - $b) <= $bound;
    }
}
