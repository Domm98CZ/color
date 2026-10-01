<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Math;

use Domm98CZ\Color\Math\Exception\InvalidDimensionException;
use Domm98CZ\Color\Math\Exception\NonFiniteValueException;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Math\Vector3;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Vector3Test extends TestCase
{
    use FloatAssertions;

    public function testConstructionAndAccess(): void
    {
        $vector = new Vector3(0.25, -0.5, 1.0);

        self::assertSame([0.25, -0.5, 1.0], $vector->toList());
        self::assertSame(0.25, $vector->component(0));
        self::assertSame(-0.5, $vector->component(1));
        self::assertSame(1.0, $vector->component(2));
        self::assertSame([0.0, 0.0, 0.0], Vector3::zero()->toList());
    }

    public function testNegativeZeroComponentsAreNormalized(): void
    {
        $vector = new Vector3(-0.0, -0.0, -0.0);

        self::assertPositiveZero($vector->x);
        self::assertPositiveZero($vector->y);
        self::assertPositiveZero($vector->z);
    }

    public function testRejectsNonFiniteComponents(): void
    {
        $this->expectException(NonFiniteValueException::class);
        $this->expectExceptionMessage('Vector component y');

        new Vector3(0.0, \NAN, 0.0);
    }

    public function testFromListAcceptsIntegersAndFloats(): void
    {
        self::assertSame([1.0, 0.5, -2.0], Vector3::fromList([1, 0.5, -2])->toList());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function invalidLists(): iterable
    {
        yield 'too short' => [[1.0, 2.0]];
        yield 'too long' => [[1.0, 2.0, 3.0, 4.0]];
        yield 'associative' => [['x' => 1.0, 'y' => 2.0, 'z' => 3.0]];
        yield 'numeric string' => [[1.0, '2.0', 3.0]];
        yield 'null' => [[1.0, null, 3.0]];
    }

    /**
     * @param array<array-key, mixed> $values
     */
    #[DataProvider('invalidLists')]
    public function testFromListRejectsInvalidShapes(array $values): void
    {
        $this->expectException(InvalidDimensionException::class);

        Vector3::fromList($values);
    }

    public function testArithmetic(): void
    {
        $a = new Vector3(1.0, 2.0, 3.0);
        $b = new Vector3(0.5, -2.0, 4.0);

        self::assertSame([1.5, 0.0, 7.0], $a->add($b)->toList());
        self::assertSame([0.5, 4.0, -1.0], $a->subtract($b)->toList());
        self::assertSame([2.0, 4.0, 6.0], $a->scale(2.0)->toList());
        self::assertSame(8.5, $a->dot($b));
        self::assertSame(5.0, (new Vector3(3.0, 4.0, 0.0))->length());
    }

    public function testLengthDoesNotOverflowForLargeComponents(): void
    {
        $length = (new Vector3(1e200, 1e200, 1e200))->length();

        self::assertFloatClose(\sqrt(3.0) * 1e200, $length, Tolerance::relative(4 * \PHP_FLOAT_EPSILON));
    }

    public function testArithmeticOverflowIsRejected(): void
    {
        $this->expectException(NonFiniteValueException::class);

        (new Vector3(\PHP_FLOAT_MAX, 0.0, 0.0))->scale(2.0);
    }

    public function testIsCloseToChecksEveryComponent(): void
    {
        $tolerance = Tolerance::absolute(1e-9); // the parameter under test, not a result tolerance
        $a = new Vector3(1.0, 2.0, 3.0);

        self::assertTrue($a->isCloseTo(new Vector3(1.0 + 1e-10, 2.0, 3.0 - 1e-10), $tolerance));
        self::assertFalse($a->isCloseTo(new Vector3(1.0, 2.0, 3.0 + 1e-8), $tolerance));
        self::assertFalse($a->isCloseTo(new Vector3(1.0 + 1e-8, 2.0, 3.0), $tolerance));
    }
}
