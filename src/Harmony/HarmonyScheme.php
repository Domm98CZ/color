<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Harmony;

use Domm98CZ\Color\Harmony\Exception\InvalidHarmonyException;

/**
 * A harmony scheme: angular offsets from the base color on a wheel (the base is offset 0).
 *
 * The named schemes follow the usual color-theory conventions (they are conventions, not a
 * standard); any other scheme can be written with {@see custom()}.
 */
final readonly class HarmonyScheme
{
    /**
     * @param non-empty-string $name
     * @param non-empty-list<float> $offsets degrees; 0 is the base color
     */
    private function __construct(
        public string $name,
        public array $offsets,
    ) {}

    /** The base and its opposite: 0°, 180°. */
    public static function complementary(): self
    {
        return new self('complementary', [0.0, 180.0]);
    }

    /**
     * The base and the two neighbours of its complement: 0°, 180° − spread, 180° + spread.
     *
     * @throws InvalidHarmonyException when the spread is not in (0°, 180°)
     */
    public static function splitComplementary(float $spread = 30.0): self
    {
        if (!($spread > 0.0 && $spread < 180.0)) {
            throw new InvalidHarmonyException(\sprintf('The split-complementary spread must be in (0, 180) degrees, %s given.', \var_export($spread, true)));
        }

        return new self(\sprintf('split-complementary[spread=%s]', self::number($spread)), [0.0, 180.0 - $spread, 180.0 + $spread]);
    }

    /**
     * $count neighbours $step apart, around the base: for 5 colors and 30° the offsets are −60°,
     * −30°, 0°, 30°, 60°; for an even count the extra color is on the positive side.
     *
     * @throws InvalidHarmonyException when the count is below 1 or the step is not finite
     */
    public static function analogous(int $count = 3, float $step = 30.0): self
    {
        if ($count < 1) {
            throw new InvalidHarmonyException(\sprintf('An analogous scheme needs at least one color, %d given.', $count));
        }

        if (!\is_finite($step)) {
            throw new InvalidHarmonyException(\sprintf('The analogous step must be finite, %s given.', \var_export($step, true)));
        }

        $first = -\intdiv($count - 1, 2);
        $offsets = \array_map(static fn(int $k): float => $k * $step, \range($first, $first + $count - 1));

        return new self(\sprintf('analogous[count=%d,step=%s]', $count, self::number($step)), $offsets);
    }

    /** Three colors 120° apart. */
    public static function triadic(): self
    {
        return new self('triadic', [0.0, 120.0, 240.0]);
    }

    /**
     * A rectangle: the base, a second color $offset away, and both complements: 0°, offset, 180°,
     * 180° + offset.
     *
     * @throws InvalidHarmonyException when the offset is not finite
     */
    public static function tetradic(float $offset = 60.0): self
    {
        if (!\is_finite($offset)) {
            throw new InvalidHarmonyException(\sprintf('The tetradic offset must be finite, %s given.', \var_export($offset, true)));
        }

        return new self(\sprintf('tetradic[offset=%s]', self::number($offset)), [0.0, $offset, 180.0, 180.0 + $offset]);
    }

    /** Four colors 90° apart. */
    public static function square(): self
    {
        return new self('square', [0.0, 90.0, 180.0, 270.0]);
    }

    /**
     * @param non-empty-string $name
     * @param list<float> $offsets degrees, finite, at least one
     *
     * @throws InvalidHarmonyException
     */
    public static function custom(string $name, array $offsets): self
    {
        if ($offsets === []) {
            throw new InvalidHarmonyException('A harmony scheme needs at least one offset.');
        }

        foreach ($offsets as $offset) {
            if (!\is_finite($offset)) {
                throw new InvalidHarmonyException(\sprintf('Harmony offsets must be finite, %s given.', \var_export($offset, true)));
            }
        }

        return new self($name, $offsets);
    }

    private static function number(float $value): string
    {
        return \rtrim(\rtrim(\sprintf('%.6F', $value), '0'), '.');
    }
}
