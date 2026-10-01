<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Math;

use Domm98CZ\Color\Math\Exception\InvalidToleranceException;
use Domm98CZ\Color\Math\Tolerance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ToleranceTest extends TestCase
{
    public function testAbsoluteBoundIsInclusive(): void
    {
        $tolerance = Tolerance::absolute(0.5);

        self::assertTrue($tolerance->isClose(1.0, 1.5));
        self::assertTrue($tolerance->isClose(1.5, 1.0));
        self::assertFalse($tolerance->isClose(1.0, 1.5000001));
        self::assertSame(0.5, $tolerance->absolute);
        self::assertSame(0.0, $tolerance->relative);
    }

    public function testRelativeBoundScalesWithTheLargerMagnitude(): void
    {
        $tolerance = Tolerance::relative(0.01);

        self::assertTrue($tolerance->isClose(100.0, 101.0));
        self::assertFalse($tolerance->isClose(100.0, 102.0));
        self::assertTrue($tolerance->isClose(-100.0, -101.0));
        self::assertFalse($tolerance->isClose(0.0, 1e-300), 'A pure relative tolerance only accepts exact zero near zero.');
    }

    public function testCombinedBoundUsesTheLargerOfBoth(): void
    {
        $tolerance = Tolerance::of(1e-3, 1e-6);

        self::assertTrue($tolerance->isClose(0.0, 0.001));
        self::assertTrue($tolerance->isClose(1e6, 1e6 + 1.0));
        self::assertFalse($tolerance->isClose(1e6, 1e6 + 2.0));
    }

    public function testExactToleranceAcceptsOnlyIdenticalValues(): void
    {
        $tolerance = Tolerance::exact();

        self::assertTrue($tolerance->isClose(0.1 + 0.2, 0.1 + 0.2));
        self::assertFalse($tolerance->isClose(0.1 + 0.2, 0.3));
        self::assertTrue($tolerance->isClose(0.0, -0.0));
    }

    public function testNonFiniteValues(): void
    {
        $tolerance = Tolerance::absolute(1.0);

        self::assertFalse($tolerance->isClose(\NAN, \NAN));
        self::assertFalse($tolerance->isClose(\NAN, 0.0));
        self::assertTrue($tolerance->isClose(\INF, \INF));
        self::assertFalse($tolerance->isClose(\INF, -\INF));
        self::assertFalse($tolerance->isClose(\INF, \PHP_FLOAT_MAX));
        self::assertFalse(Tolerance::relative(0.1)->isClose(\INF, 1.0), 'An infinity is never close to a finite value, even with a relative bound.');
        self::assertFalse(Tolerance::relative(0.1)->isClose(1.0, -\INF));
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function invalidBounds(): iterable
    {
        yield 'negative' => [-1e-12];
        yield 'NAN' => [\NAN];
        yield 'INF' => [\INF];
    }

    #[DataProvider('invalidBounds')]
    public function testRejectsInvalidAbsoluteBound(float $bound): void
    {
        $this->expectException(InvalidToleranceException::class);

        Tolerance::absolute($bound);
    }

    #[DataProvider('invalidBounds')]
    public function testRejectsInvalidRelativeBound(float $bound): void
    {
        $this->expectException(InvalidToleranceException::class);

        Tolerance::relative($bound);
    }
}
