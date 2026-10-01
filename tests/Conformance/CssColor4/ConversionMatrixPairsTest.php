<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Conformance\CssColor4;

use Domm98CZ\Color\Math\Matrix3;
use Domm98CZ\Color\Tests\Support\Fixture\FixtureLoader;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Matrix3::inverse() reproduces the inverse matrices published by CSS Color 4.
 */
final class ConversionMatrixPairsTest extends TestCase
{
    use FloatAssertions;

    private const string FIXTURE = 'css-color-4/conversion-matrix-pairs.json';

    /**
     * @return iterable<string, array{Matrix3, Matrix3}>
     */
    public static function pairs(): iterable
    {
        foreach (FixtureLoader::reference(self::FIXTURE)->cases as $case) {
            $name = $case['name'] ?? null;
            \assert(\is_string($name));

            yield $name => [
                Matrix3::fromRows(FixtureLoader::matrix($case['forward'] ?? null)),
                Matrix3::fromRows(FixtureLoader::matrix($case['inverse'] ?? null)),
            ];
        }
    }

    #[DataProvider('pairs')]
    public function testInverseOfForwardMatchesPublishedInverse(Matrix3 $forward, Matrix3 $publishedInverse): void
    {
        $tolerance = FixtureLoader::reference(self::FIXTURE)->tolerance;

        self::assertMatrixClose($publishedInverse, $forward->inverse(), $tolerance);
    }

    #[DataProvider('pairs')]
    public function testInverseOfPublishedInverseMatchesForward(Matrix3 $forward, Matrix3 $publishedInverse): void
    {
        $tolerance = FixtureLoader::reference(self::FIXTURE)->tolerance;

        self::assertMatrixClose($forward, $publishedInverse->inverse(), $tolerance);
    }

    #[DataProvider('pairs')]
    public function testPublishedPairMultipliesToIdentity(Matrix3 $forward, Matrix3 $publishedInverse): void
    {
        self::assertMatrixClose(Matrix3::identity(), $forward->multiply($publishedInverse), Tolerances::linearAlgebra());
        self::assertMatrixClose(Matrix3::identity(), $publishedInverse->multiply($forward), Tolerances::linearAlgebra());
    }
}
