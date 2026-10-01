<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Conformance\CssColor4;

use Domm98CZ\Color\Math\Matrix3;
use Domm98CZ\Color\Space\ColorSpaceRegistry;
use Domm98CZ\Color\Space\Definition\LinearRgbSpace;
use Domm98CZ\Color\Tests\Support\Fixture\FixtureLoader;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * RGB ↔ XYZ matrices computed from primaries (SMPTE RP 177) reproduce the literals of CSS Color 4.
 */
final class RgbXyzMatricesTest extends TestCase
{
    use FloatAssertions;

    private const string FIXTURE = 'css-color-4/rgb-xyz-matrices.json';

    /**
     * @return iterable<string, array{string, Matrix3, Matrix3}>
     */
    public static function spaces(): iterable
    {
        foreach (FixtureLoader::reference(self::FIXTURE)->cases as $case) {
            $space = $case['space'] ?? null;
            \assert(\is_string($space));

            yield $space => [
                $space,
                Matrix3::fromRows(FixtureLoader::matrix($case['to_xyz'] ?? null)),
                Matrix3::fromRows(FixtureLoader::matrix($case['from_xyz'] ?? null)),
            ];
        }
    }

    #[DataProvider('spaces')]
    public function testMatricesMatchPublishedLiterals(string $spaceId, Matrix3 $toXyz, Matrix3 $fromXyz): void
    {
        $space = ColorSpaceRegistry::default()->get($spaceId);
        self::assertInstanceOf(LinearRgbSpace::class, $space);
        $tolerance = FixtureLoader::reference(self::FIXTURE)->tolerance;

        self::assertMatrixClose($toXyz, $space->toXyzMatrix(), $tolerance, $spaceId . ' RGB -> XYZ');
        self::assertMatrixClose($fromXyz, $space->toXyzMatrix()->inverse(), $tolerance, $spaceId . ' XYZ -> RGB');
    }
}
