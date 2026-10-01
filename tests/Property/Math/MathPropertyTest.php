<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Property\Math;

use Domm98CZ\Color\Math\Angle;
use Domm98CZ\Color\Math\Matrix3;
use Domm98CZ\Color\Math\Numeric;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Math\Vector3;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\SeededRandom;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\TestCase;

/**
 * Invariants of the Math module over seeded random inputs.
 */
final class MathPropertyTest extends TestCase
{
    use FloatAssertions;

    private const int ITERATIONS = 500;

    public function testNormalizedAngleIsInRangeAndIdempotent(): void
    {
        $random = SeededRandom::fromEnvironment();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $angle = $random->float(-10_000.0, 10_000.0);
            $normalized = Angle::normalizeDegrees($angle);

            self::assertGreaterThanOrEqual(0.0, $normalized, $random->describe());
            self::assertLessThan(360.0, $normalized, $random->describe());
            self::assertSame($normalized, Angle::normalizeDegrees($normalized), $random->describe());
            self::assertAngleClose($angle, $normalized, Tolerances::ANGLE_DEGREES, $random->describe());
        }
    }

    public function testSignedDifferenceRotatesFromOntoTo(): void
    {
        $random = SeededRandom::fromEnvironment();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $from = $random->float(0.0, 360.0);
            $to = $random->float(0.0, 360.0);
            $difference = Angle::signedDifferenceDegrees($from, $to);

            self::assertGreaterThan(-180.0, $difference, $random->describe());
            self::assertLessThanOrEqual(180.0, $difference, $random->describe());
            self::assertAngleClose($to, $from + $difference, Tolerances::ANGLE_DEGREES, $random->describe());
        }
    }

    public function testCbrtInvertsCubing(): void
    {
        $random = SeededRandom::fromEnvironment();
        $tolerance = Tolerance::relative(Tolerances::CBRT_RELATIVE);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $x = $random->float(-2.0, 2.0);

            self::assertFloatClose($x, Numeric::cbrt($x * $x * $x), $tolerance, $random->describe());
        }
    }

    /**
     * Diagonally dominant matrices with entries of magnitude ~1 are well conditioned,
     * like the color-transform matrices the library uses.
     */
    public function testInverseRoundTripsOnWellConditionedMatrices(): void
    {
        $random = SeededRandom::fromEnvironment();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $rows = [];
            for ($r = 0; $r < 3; $r++) {
                $row = [$random->float(-1.0, 1.0), $random->float(-1.0, 1.0), $random->float(-1.0, 1.0)];
                $row[$r] = ($row[$r] < 0.0 ? -1.0 : 1.0) * (3.0 + \abs($row[$r]));
                $rows[] = $row;
            }

            $matrix = Matrix3::fromRows($rows);
            $vector = new Vector3($random->float(-1.0, 1.0), $random->float(-1.0, 1.0), $random->float(-1.0, 1.0));

            self::assertMatrixClose(Matrix3::identity(), $matrix->multiply($matrix->inverse()), Tolerances::linearAlgebra(), $random->describe());
            self::assertVectorClose($vector, $matrix->inverse()->transform($matrix->transform($vector)), Tolerances::linearAlgebra(), $random->describe());
        }
    }
}
