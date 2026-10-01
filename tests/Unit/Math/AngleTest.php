<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Math;

use Domm98CZ\Color\Math\Angle;
use Domm98CZ\Color\Math\Exception\NonFiniteValueException;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AngleTest extends TestCase
{
    use FloatAssertions;

    /**
     * fmod() is exact, so these results are exact by definition.
     *
     * @return iterable<string, array{float, float}>
     */
    public static function normalizations(): iterable
    {
        yield 'zero' => [0.0, 0.0];
        yield 'inside' => [123.5, 123.5];
        yield 'just below 360' => [359.9999999, 359.9999999];
        yield 'full turn' => [360.0, 0.0];
        yield 'two turns' => [720.0, 0.0];
        yield 'negative' => [-30.0, 330.0];
        yield 'negative full turn' => [-360.0, 0.0];
        yield 'above full turn' => [725.0, 5.0];
        yield 'large negative' => [-1085.0, 355.0];
    }

    #[DataProvider('normalizations')]
    public function testNormalizeDegrees(float $input, float $expected): void
    {
        self::assertSame($expected, Angle::normalizeDegrees($input));
    }

    public function testNormalizeNegativeZeroGivesPositiveZero(): void
    {
        self::assertPositiveZero(Angle::normalizeDegrees(-0.0));
        self::assertPositiveZero(Angle::normalizeDegrees(-360.0));
    }

    public function testTinyNegativeAngleDoesNotBecome360(): void
    {
        // -1e-15 + 360 rounds to exactly 360.0 in float64; the result must stay in [0, 360).
        $normalized = Angle::normalizeDegrees(-1e-15);

        self::assertGreaterThanOrEqual(0.0, $normalized);
        self::assertLessThan(360.0, $normalized);
        self::assertAngleClose(0.0, $normalized, Tolerances::ANGLE_DEGREES);
    }

    #[DataProvider('nonFinite')]
    public function testNormalizeRejectsNonFinite(float $angle): void
    {
        $this->expectException(NonFiniteValueException::class);

        Angle::normalizeDegrees($angle);
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function nonFinite(): iterable
    {
        yield 'NAN' => [\NAN];
        yield 'INF' => [\INF];
        yield '-INF' => [-\INF];
    }

    /**
     * @return iterable<string, array{float, float, float}>
     */
    public static function signedDifferences(): iterable
    {
        yield 'forward' => [10.0, 30.0, 20.0];
        yield 'backward' => [30.0, 10.0, -20.0];
        yield 'across zero forward' => [350.0, 10.0, 20.0];
        yield 'across zero backward' => [10.0, 350.0, -20.0];
        yield 'opposite is +180' => [0.0, 180.0, 180.0];
        yield 'opposite reversed is +180' => [180.0, 0.0, 180.0];
        yield 'opposite elsewhere' => [90.0, 270.0, 180.0];
        yield 'same' => [42.0, 42.0, 0.0];
        yield 'unnormalized inputs' => [-10.0, 370.0, 20.0];
    }

    #[DataProvider('signedDifferences')]
    public function testSignedDifference(float $from, float $to, float $expected): void
    {
        self::assertSame($expected, Angle::signedDifferenceDegrees($from, $to));
    }

    public function testDistanceIsSymmetricAndWithinZeroTo180(): void
    {
        self::assertSame(20.0, Angle::distanceDegrees(350.0, 10.0));
        self::assertSame(20.0, Angle::distanceDegrees(10.0, 350.0));
        self::assertSame(180.0, Angle::distanceDegrees(0.0, 180.0));
        self::assertSame(0.0, Angle::distanceDegrees(0.0, 360.0));
    }

    public function testDegreeRadianConversions(): void
    {
        self::assertSame(\M_PI, Angle::degreesToRadians(180.0));
        self::assertSame(180.0, Angle::radiansToDegrees(\M_PI));

        $this->expectException(NonFiniteValueException::class);
        Angle::radiansToDegrees(\NAN);
    }
}
