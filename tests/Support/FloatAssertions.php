<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Support;

use Domm98CZ\Color\Math\Angle;
use Domm98CZ\Color\Math\Matrix3;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Math\Vector3;
use PHPUnit\Framework\Assert;

/**
 * Tolerance-aware assertions. Floating-point results are never compared with assertSame()
 * unless the value is exact by definition.
 */
trait FloatAssertions
{
    public static function assertFloatClose(float $expected, float $actual, Tolerance $tolerance, string $message = ''): void
    {
        Assert::assertTrue(
            $tolerance->isClose($expected, $actual),
            self::floatFailure($expected, $actual, $tolerance, $message),
        );
    }

    public static function assertVectorClose(Vector3 $expected, Vector3 $actual, Tolerance $tolerance, string $message = ''): void
    {
        foreach ([0, 1, 2] as $index) {
            self::assertFloatClose(
                $expected->component($index),
                $actual->component($index),
                $tolerance,
                \trim($message . ' (component ' . $index . ')'),
            );
        }
    }

    public static function assertMatrixClose(Matrix3 $expected, Matrix3 $actual, Tolerance $tolerance, string $message = ''): void
    {
        foreach ([0, 1, 2] as $row) {
            foreach ([0, 1, 2] as $column) {
                self::assertFloatClose(
                    $expected->get($row, $column),
                    $actual->get($row, $column),
                    $tolerance,
                    \trim(\sprintf('%s (element [%d][%d])', $message, $row, $column)),
                );
            }
        }
    }

    /**
     * Cyclic comparison of angles in degrees: 359.9999999 and 0 are close.
     */
    public static function assertAngleClose(float $expectedDegrees, float $actualDegrees, float $toleranceDegrees, string $message = ''): void
    {
        $distance = Angle::distanceDegrees($expectedDegrees, $actualDegrees);

        Assert::assertLessThanOrEqual(
            $toleranceDegrees,
            $distance,
            \trim(\sprintf('%s Angles %s° and %s° differ by %s° (tolerance %s°).', $message, \var_export($expectedDegrees, true), \var_export($actualDegrees, true), \var_export($distance, true), \var_export($toleranceDegrees, true))),
        );
    }

    /**
     * Asserts positive zero (not -0.0).
     */
    public static function assertPositiveZero(float $actual, string $message = ''): void
    {
        Assert::assertSame(\INF, \fdiv(1.0, $actual), \trim($message . ' Expected +0.0.'));
    }

    private static function floatFailure(float $expected, float $actual, Tolerance $tolerance, string $message): string
    {
        return \trim(\sprintf(
            '%s Expected %s, got %s (difference %s; tolerance abs %s, rel %s).',
            $message,
            \var_export($expected, true),
            \var_export($actual, true),
            \var_export(\abs($expected - $actual), true),
            \var_export($tolerance->absolute, true),
            \var_export($tolerance->relative, true),
        ));
    }
}
