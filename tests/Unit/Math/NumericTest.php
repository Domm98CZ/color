<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Math;

use Domm98CZ\Color\Math\Exception\NonFiniteValueException;
use Domm98CZ\Color\Math\Exception\OutOfDomainException;
use Domm98CZ\Color\Math\Numeric;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NumericTest extends TestCase
{
    use FloatAssertions;

    /**
     * Perfect cubes must come out exact: cbrt(27) is 3.0, not 3.0000000000000004.
     *
     * @return iterable<string, array{float, float}>
     */
    public static function perfectCubes(): iterable
    {
        yield '1' => [1.0, 1.0];
        yield '8' => [8.0, 2.0];
        yield '27' => [27.0, 3.0];
        yield '64' => [64.0, 4.0];
        yield '1000' => [1000.0, 10.0];
        yield '0.125' => [0.125, 0.5];
        yield '-8' => [-8.0, -2.0];
        yield '-27' => [-27.0, -3.0];
        yield '-0.125' => [-0.125, -0.5];
    }

    #[DataProvider('perfectCubes')]
    public function testCbrtOfPerfectCubeIsExact(float $x, float $expected): void
    {
        self::assertSame($expected, Numeric::cbrt($x));
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function arbitraryValues(): iterable
    {
        yield 'two' => [2.0];
        yield 'D50 white Z' => [0.8251046025104602];
        yield 'Lab epsilon 216/24389' => [216 / 24389];
        yield 'tiny' => [1e-300];
        yield 'subnormal' => [5e-320];
        yield 'huge' => [1e300];
        yield 'negative' => [-0.3];
    }

    #[DataProvider('arbitraryValues')]
    public function testCbrtCubedGivesBackTheArgument(float $x): void
    {
        $root = Numeric::cbrt($x);

        // Relative error of r³ is about 3× the relative error of r.
        self::assertFloatClose($x, $root * $root * $root, Tolerance::relative(3 * Tolerances::CBRT_RELATIVE));
        self::assertSame($x < 0.0, $root < 0.0);
    }

    public function testCbrtOfZeroIsPositiveZero(): void
    {
        self::assertPositiveZero(Numeric::cbrt(0.0));
        self::assertPositiveZero(Numeric::cbrt(-0.0));
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

    #[DataProvider('nonFinite')]
    public function testCbrtRejectsNonFiniteInput(float $x): void
    {
        $this->expectException(NonFiniteValueException::class);

        Numeric::cbrt($x);
    }

    public function testSignedPowMirrorsNegativeBase(): void
    {
        self::assertSame(4.0, Numeric::signedPow(2.0, 2.0));
        self::assertSame(-4.0, Numeric::signedPow(-2.0, 2.0));
        self::assertFloatClose(-(0.5 ** 2.4), Numeric::signedPow(-0.5, 2.4), Tolerance::relative(\PHP_FLOAT_EPSILON));
        self::assertPositiveZero(Numeric::signedPow(0.0, 2.4));
        self::assertPositiveZero(Numeric::signedPow(-0.0, 2.4));
        self::assertSame(-0.5, Numeric::signedPow(-2.0, -1.0));
    }

    /**
     * @return iterable<string, array{float, float}>
     */
    public static function zeroToNonPositivePower(): iterable
    {
        yield '0^0' => [0.0, 0.0];
        yield '0^-1' => [0.0, -1.0];
        yield '-0^-2.4' => [-0.0, -2.4];
    }

    #[DataProvider('zeroToNonPositivePower')]
    public function testSignedPowRejectsZeroToNonPositivePower(float $base, float $exponent): void
    {
        $this->expectException(OutOfDomainException::class);

        Numeric::signedPow($base, $exponent);
    }

    #[DataProvider('nonFinite')]
    public function testSignedPowRejectsNonFiniteBase(float $x): void
    {
        $this->expectException(NonFiniteValueException::class);

        Numeric::signedPow($x, 2.0);
    }

    #[DataProvider('nonFinite')]
    public function testSignedPowRejectsNonFiniteExponent(float $x): void
    {
        $this->expectException(NonFiniteValueException::class);

        Numeric::signedPow(2.0, $x);
    }

    public function testNormalizeZero(): void
    {
        self::assertPositiveZero(Numeric::normalizeZero(-0.0));
        self::assertPositiveZero(Numeric::normalizeZero(0.0));
        self::assertSame(-1.5, Numeric::normalizeZero(-1.5));
        self::assertSame(\PHP_FLOAT_MIN, Numeric::normalizeZero(\PHP_FLOAT_MIN));
    }

    public function testAssertFiniteReturnsValueOrThrowsWithName(): void
    {
        self::assertSame(1.25, Numeric::assertFinite(1.25, 'Value'));

        $this->expectException(NonFiniteValueException::class);
        $this->expectExceptionMessage('Lightness must be a finite number, NAN given.');

        Numeric::assertFinite(\NAN, 'Lightness');
    }

    public function testNonFiniteMessageDistinguishesInfinities(): void
    {
        self::assertStringContainsString('-INF given', NonFiniteValueException::for('x', -\INF)->getMessage());
        self::assertStringContainsString(' INF given', NonFiniteValueException::for('x', \INF)->getMessage());
    }
}
