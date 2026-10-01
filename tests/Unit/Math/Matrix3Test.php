<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Math;

use Domm98CZ\Color\Math\Exception\InvalidDimensionException;
use Domm98CZ\Color\Math\Exception\NonFiniteValueException;
use Domm98CZ\Color\Math\Exception\SingularMatrixException;
use Domm98CZ\Color\Math\Matrix3;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Math\Vector3;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Matrix3Test extends TestCase
{
    use FloatAssertions;

    private static function sample(): Matrix3
    {
        return Matrix3::fromRows([[2, 1, 0], [1, 3, 1], [0, 1, 4]]);
    }

    public function testFromRowsAndAccess(): void
    {
        $matrix = self::sample();

        self::assertSame([[2.0, 1.0, 0.0], [1.0, 3.0, 1.0], [0.0, 1.0, 4.0]], $matrix->toRows());
        self::assertSame(3.0, $matrix->get(1, 1));
        self::assertSame(1.0, $matrix->get(2, 1));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function invalidRows(): iterable
    {
        yield 'two rows' => [[[1, 0, 0], [0, 1, 0]]];
        yield 'short row' => [[[1, 0, 0], [0, 1], [0, 0, 1]]];
        yield 'row is not an array' => [[[1, 0, 0], 5, [0, 0, 1]]];
        yield 'associative rows' => [['a' => [1, 0, 0], 'b' => [0, 1, 0], 'c' => [0, 0, 1]]];
    }

    /**
     * @param array<array-key, mixed> $rows
     */
    #[DataProvider('invalidRows')]
    public function testFromRowsRejectsInvalidShapes(array $rows): void
    {
        $this->expectException(InvalidDimensionException::class);

        Matrix3::fromRows($rows);
    }

    public function testFromRowsRejectsNonFiniteValues(): void
    {
        $this->expectException(NonFiniteValueException::class);

        Matrix3::fromRows([[1, 0, 0], [0, \INF, 0], [0, 0, 1]]);
    }

    public function testIdentityAndDiagonal(): void
    {
        self::assertSame([[1.0, 0.0, 0.0], [0.0, 1.0, 0.0], [0.0, 0.0, 1.0]], Matrix3::identity()->toRows());
        self::assertSame([[2.0, 0.0, 0.0], [0.0, 3.0, 0.0], [0.0, 0.0, 4.0]], Matrix3::diagonal(2.0, 3.0, 4.0)->toRows());
    }

    public function testMultiplyIsRowByColumn(): void
    {
        $a = Matrix3::fromRows([[1, 2, 3], [4, 5, 6], [7, 8, 9]]);
        $b = Matrix3::fromRows([[9, 8, 7], [6, 5, 4], [3, 2, 1]]);

        self::assertSame([[30.0, 24.0, 18.0], [84.0, 69.0, 54.0], [138.0, 114.0, 90.0]], $a->multiply($b)->toRows());
        self::assertSame($a->toRows(), $a->multiply(Matrix3::identity())->toRows());
        self::assertSame($a->toRows(), Matrix3::identity()->multiply($a)->toRows());
    }

    public function testTransform(): void
    {
        $result = self::sample()->transform(new Vector3(1.0, 2.0, 3.0));

        self::assertSame([4.0, 10.0, 14.0], $result->toList());
    }

    public function testTranspose(): void
    {
        $matrix = Matrix3::fromRows([[1, 2, 3], [4, 5, 6], [7, 8, 9]]);

        self::assertSame([[1.0, 4.0, 7.0], [2.0, 5.0, 8.0], [3.0, 6.0, 9.0]], $matrix->transpose()->toRows());
    }

    public function testDeterminant(): void
    {
        self::assertSame(18.0, self::sample()->determinant());
        self::assertSame(24.0, Matrix3::diagonal(2.0, 3.0, 4.0)->determinant());
        self::assertSame(0.0, Matrix3::fromRows([[1, 2, 3], [4, 5, 6], [7, 8, 9]])->determinant());
    }

    public function testInverseOfDiagonalIsExact(): void
    {
        self::assertSame(
            [[0.5, 0.0, 0.0], [0.0, 0.25, 0.0], [0.0, 0.0, 0.125]],
            Matrix3::diagonal(2.0, 4.0, 8.0)->inverse()->toRows(),
        );
    }

    public function testInverseOfSample(): void
    {
        $expected = Matrix3::fromRows([
            [11 / 18, -4 / 18, 1 / 18],
            [-4 / 18, 8 / 18, -2 / 18],
            [1 / 18, -2 / 18, 5 / 18],
        ]);

        self::assertMatrixClose($expected, self::sample()->inverse(), Tolerances::linearAlgebra());
        self::assertMatrixClose(Matrix3::identity(), self::sample()->multiply(self::sample()->inverse()), Tolerances::linearAlgebra());
    }

    public function testInverseIsScaleIndependent(): void
    {
        $tiny = Matrix3::diagonal(1e-100, 2e-100, 4e-100);

        self::assertMatrixClose(Matrix3::diagonal(1e100, 5e99, 2.5e99), $tiny->inverse(), Tolerance::relative(4 * \PHP_FLOAT_EPSILON));
    }

    /**
     * @return iterable<string, array{Matrix3}>
     */
    public static function singularMatrices(): iterable
    {
        yield 'zero' => [Matrix3::fromRows([[0, 0, 0], [0, 0, 0], [0, 0, 0]])];
        yield 'dependent rows' => [Matrix3::fromRows([[1, 2, 3], [4, 5, 6], [7, 8, 9]])];
        yield 'zero column' => [Matrix3::fromRows([[1, 0, 3], [4, 0, 6], [7, 0, 9]])];
        yield 'nearly dependent' => [Matrix3::fromRows([[1, 2, 3], [2, 4, 6.000000000000001], [1, 0, 1]])];
    }

    #[DataProvider('singularMatrices')]
    public function testInverseRejectsSingularMatrices(Matrix3 $matrix): void
    {
        $this->expectException(SingularMatrixException::class);

        $matrix->inverse();
    }

    public function testIsCloseTo(): void
    {
        $a = self::sample();
        $b = Matrix3::fromRows([[2, 1, 0], [1, 3 + 1e-13, 1], [0, 1, 4]]);
        $c = Matrix3::fromRows([[2, 1, 0], [1, 3, 1], [0, 1, 4.001]]);

        self::assertTrue($a->isCloseTo($b, Tolerances::linearAlgebra()));
        self::assertFalse($a->isCloseTo($c, Tolerances::linearAlgebra()));
    }
}
