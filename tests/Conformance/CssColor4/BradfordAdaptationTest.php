<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Conformance\CssColor4;

use Domm98CZ\Color\Colorimetry\Algorithm\Bradford;
use Domm98CZ\Color\Colorimetry\WhitePoints;
use Domm98CZ\Color\Math\Matrix3;
use Domm98CZ\Color\Tests\Support\Fixture\FixtureLoader;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use PHPUnit\Framework\TestCase;

/**
 * The linear Bradford matrices computed from the Bradford cone response matrix and the CSS whites
 * reproduce the D65_to_D50 / D50_to_D65 literals of CSS Color 4.
 */
final class BradfordAdaptationTest extends TestCase
{
    use FloatAssertions;

    private const string FIXTURE = 'css-color-4/conversion-matrix-pairs.json';

    public function testComputedMatricesMatchPublishedLiterals(): void
    {
        $fixture = FixtureLoader::reference(self::FIXTURE);
        $case = null;
        foreach ($fixture->cases as $candidate) {
            if (($candidate['name'] ?? null) === 'linear Bradford chromatic adaptation D65 -> D50') {
                $case = $candidate;
            }
        }
        self::assertNotNull($case, 'The Bradford case must exist in the fixture.');

        $bradford = new Bradford();

        self::assertMatrixClose(
            Matrix3::fromRows(FixtureLoader::matrix($case['forward'] ?? null)),
            $bradford->matrix(WhitePoints::d65(), WhitePoints::d50()),
            $fixture->tolerance,
            'D65 -> D50',
        );
        self::assertMatrixClose(
            Matrix3::fromRows(FixtureLoader::matrix($case['inverse'] ?? null)),
            $bradford->matrix(WhitePoints::d50(), WhitePoints::d65()),
            $fixture->tolerance,
            'D50 -> D65',
        );
    }
}
