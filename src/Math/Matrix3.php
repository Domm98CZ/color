<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Math;

use Domm98CZ\Color\Math\Exception\InvalidDimensionException;
use Domm98CZ\Color\Math\Exception\SingularMatrixException;

/**
 * Immutable 3×3 matrix of finite floats, stored row-major.
 *
 * Used for linear color transforms (RGB ↔ XYZ, chromatic adaptation, XYZ ↔ LMS).
 */
final readonly class Matrix3
{
    /**
     * Relative singularity threshold used by {@see inverse()}: the matrix is treated as singular
     * when |det| ≤ SINGULARITY_FACTOR · ε · Π‖rowᵢ‖₂ (ε = PHP_FLOAT_EPSILON). By Hadamard's
     * inequality |det| ≤ Π‖rowᵢ‖₂, so the ratio is a scale-independent measure of how close the rows
     * are to linear dependence. Color-transform matrices are far from this bound.
     */
    public const float SINGULARITY_FACTOR = 64.0;

    /**
     * @param list<float> $values nine finite values, row-major
     */
    private function __construct(private array $values) {}

    /**
     * @param array<array-key, mixed> $rows three rows of three numbers each
     *
     * @throws InvalidDimensionException
     */
    public static function fromRows(array $rows): self
    {
        if (!\array_is_list($rows) || \count($rows) !== 3) {
            throw InvalidDimensionException::expected('Matrix3 input', 'a list of exactly 3 rows');
        }

        $values = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                throw InvalidDimensionException::expected('Each Matrix3 row', 'a list of exactly 3 numbers');
            }

            foreach (Vector3::fromList($row)->toList() as $value) {
                $values[] = $value;
            }
        }

        return new self($values);
    }

    public static function identity(): self
    {
        return self::diagonal(1.0, 1.0, 1.0);
    }

    public static function diagonal(float $a, float $b, float $c): self
    {
        return self::fromRows([[$a, 0.0, 0.0], [0.0, $b, 0.0], [0.0, 0.0, $c]]);
    }

    /**
     * @return array{array{float, float, float}, array{float, float, float}, array{float, float, float}}
     */
    public function toRows(): array
    {
        $v = $this->values;

        return [[$v[0], $v[1], $v[2]], [$v[3], $v[4], $v[5]], [$v[6], $v[7], $v[8]]];
    }

    /**
     * @param int<0, 2> $row
     * @param int<0, 2> $column
     */
    public function get(int $row, int $column): float
    {
        return $this->values[$row * 3 + $column];
    }

    /**
     * Matrix product $this · $other.
     */
    public function multiply(self $other): self
    {
        $a = $this->values;
        $b = $other->values;
        $rows = [];

        for ($i = 0; $i < 3; $i++) {
            $row = [];
            for ($j = 0; $j < 3; $j++) {
                $row[] = $a[$i * 3] * $b[$j] + $a[$i * 3 + 1] * $b[3 + $j] + $a[$i * 3 + 2] * $b[6 + $j];
            }
            $rows[] = $row;
        }

        return self::fromRows($rows);
    }

    /**
     * Matrix–vector product $this · $vector.
     */
    public function transform(Vector3 $vector): Vector3
    {
        $m = $this->values;

        return new Vector3(
            $m[0] * $vector->x + $m[1] * $vector->y + $m[2] * $vector->z,
            $m[3] * $vector->x + $m[4] * $vector->y + $m[5] * $vector->z,
            $m[6] * $vector->x + $m[7] * $vector->y + $m[8] * $vector->z,
        );
    }

    public function transpose(): self
    {
        $v = $this->values;

        return self::fromRows([[$v[0], $v[3], $v[6]], [$v[1], $v[4], $v[7]], [$v[2], $v[5], $v[8]]]);
    }

    public function determinant(): float
    {
        [$a, $b, $c, $d, $e, $f, $g, $h, $i] = $this->values;

        return $a * ($e * $i - $f * $h) - $b * ($d * $i - $f * $g) + $c * ($d * $h - $e * $g);
    }

    /**
     * Inverse via the adjugate (cofactor) formula.
     *
     * @throws SingularMatrixException when the matrix is singular or numerically close to singular
     *                                 (see {@see SINGULARITY_FACTOR})
     */
    public function inverse(): self
    {
        [$a, $b, $c, $d, $e, $f, $g, $h, $i] = $this->values;

        $determinant = $this->determinant();
        $threshold = self::SINGULARITY_FACTOR * \PHP_FLOAT_EPSILON
            * \hypot(\hypot($a, $b), $c) * \hypot(\hypot($d, $e), $f) * \hypot(\hypot($g, $h), $i);

        if (!(\abs($determinant) > $threshold)) {
            throw SingularMatrixException::withDeterminant($determinant, $threshold);
        }

        $inverse = 1.0 / $determinant;

        return self::fromRows([
            [($e * $i - $f * $h) * $inverse, ($c * $h - $b * $i) * $inverse, ($b * $f - $c * $e) * $inverse],
            [($f * $g - $d * $i) * $inverse, ($a * $i - $c * $g) * $inverse, ($c * $d - $a * $f) * $inverse],
            [($d * $h - $e * $g) * $inverse, ($b * $g - $a * $h) * $inverse, ($a * $e - $b * $d) * $inverse],
        ]);
    }

    /**
     * Element-wise closeness under the given tolerance.
     */
    public function isCloseTo(self $other, Tolerance $tolerance): bool
    {
        foreach ($this->values as $index => $value) {
            if (!$tolerance->isClose($value, $other->values[$index])) {
                return false;
            }
        }

        return true;
    }
}
