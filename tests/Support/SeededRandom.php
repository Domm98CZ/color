<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Support;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Deterministic random source for property tests. No global state is touched.
 *
 * The seed defaults to a fixed value and can be overridden with the environment variable
 * COLORS_TEST_SEED to explore other inputs. Every failure message must include {@see describe()}
 * so that the failing case can be reproduced.
 */
final class SeededRandom
{
    public const int DEFAULT_SEED = 20260928;

    private readonly Randomizer $randomizer;

    public function __construct(public readonly int $seed)
    {
        $this->randomizer = new Randomizer(new Xoshiro256StarStar($seed));
    }

    public static function fromEnvironment(): self
    {
        $configured = \getenv('COLORS_TEST_SEED');

        if (\is_string($configured) && \preg_match('~^-?\d+$~', $configured) === 1) {
            return new self((int) $configured);
        }

        return new self(self::DEFAULT_SEED);
    }

    /**
     * Uniform float in the closed interval [$min, $max].
     */
    public function float(float $min, float $max): float
    {
        return $this->randomizer->getFloat($min, $max, \Random\IntervalBoundary::ClosedClosed);
    }

    public function int(int $min, int $max): int
    {
        return $this->randomizer->getInt($min, $max);
    }

    public function describe(): string
    {
        return \sprintf('[seed %d; rerun with COLORS_TEST_SEED=%d]', $this->seed, $this->seed);
    }
}
