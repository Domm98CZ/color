<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Math;

use Domm98CZ\Color\Math\Exception\InvalidDimensionException;

/**
 * Immutable three-component vector of finite floats (e.g. XYZ tristimulus values, linear RGB).
 */
final readonly class Vector3
{
    public float $x;
    public float $y;
    public float $z;

    public function __construct(float $x, float $y, float $z)
    {
        $this->x = Numeric::normalizeZero(Numeric::assertFinite($x, 'Vector component x'));
        $this->y = Numeric::normalizeZero(Numeric::assertFinite($y, 'Vector component y'));
        $this->z = Numeric::normalizeZero(Numeric::assertFinite($z, 'Vector component z'));
    }

    /**
     * @param array<array-key, mixed> $values a list of exactly three numbers
     *
     * @throws InvalidDimensionException
     */
    public static function fromList(array $values): self
    {
        if (!\array_is_list($values) || \count($values) !== 3) {
            throw InvalidDimensionException::expected('Vector3 input', 'a list of exactly 3 numbers');
        }

        return new self(self::number($values[0]), self::number($values[1]), self::number($values[2]));
    }

    public static function zero(): self
    {
        return new self(0.0, 0.0, 0.0);
    }

    /**
     * @return array{float, float, float}
     */
    public function toList(): array
    {
        return [$this->x, $this->y, $this->z];
    }

    /**
     * @param int<0, 2> $index
     */
    public function component(int $index): float
    {
        return match ($index) {
            0 => $this->x,
            1 => $this->y,
            2 => $this->z,
        };
    }

    public function add(self $other): self
    {
        return new self($this->x + $other->x, $this->y + $other->y, $this->z + $other->z);
    }

    public function subtract(self $other): self
    {
        return new self($this->x - $other->x, $this->y - $other->y, $this->z - $other->z);
    }

    public function scale(float $factor): self
    {
        return new self($this->x * $factor, $this->y * $factor, $this->z * $factor);
    }

    public function dot(self $other): float
    {
        return $this->x * $other->x + $this->y * $other->y + $this->z * $other->z;
    }

    /**
     * Euclidean length, computed without intermediate overflow.
     */
    public function length(): float
    {
        return \hypot(\hypot($this->x, $this->y), $this->z);
    }

    /**
     * Component-wise closeness under the given tolerance.
     */
    public function isCloseTo(self $other, Tolerance $tolerance): bool
    {
        return $tolerance->isClose($this->x, $other->x)
            && $tolerance->isClose($this->y, $other->y)
            && $tolerance->isClose($this->z, $other->z);
    }

    private static function number(mixed $value): float
    {
        if (!\is_int($value) && !\is_float($value)) {
            throw InvalidDimensionException::expected('Vector3 input', 'a list of exactly 3 numbers');
        }

        return (float) $value;
    }
}
