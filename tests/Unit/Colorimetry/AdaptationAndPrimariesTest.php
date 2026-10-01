<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Colorimetry;

use Domm98CZ\Color\Colorimetry\Algorithm\Bradford;
use Domm98CZ\Color\Colorimetry\Chromaticity;
use Domm98CZ\Color\Colorimetry\RgbPrimaries;
use Domm98CZ\Color\Colorimetry\WhitePoints;
use Domm98CZ\Color\Math\Exception\SingularMatrixException;
use Domm98CZ\Color\Math\Matrix3;
use Domm98CZ\Color\Math\Vector3;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\TestCase;

final class AdaptationAndPrimariesTest extends TestCase
{
    use FloatAssertions;

    public function testNormalisedPrimaryMatrixMapsRgbWhiteToTheWhitePoint(): void
    {
        $primaries = new RgbPrimaries(new Chromaticity(0.64, 0.33), new Chromaticity(0.30, 0.60), new Chromaticity(0.15, 0.06));
        $matrix = $primaries->toXyzMatrix(WhitePoints::d65());

        self::assertVectorClose(WhitePoints::d65()->xyz, $matrix->transform(new Vector3(1.0, 1.0, 1.0)), Tolerances::linearAlgebra());
        // The second row is the luminance of each primary; it sums to 1.
        self::assertFloatClose(1.0, $matrix->get(1, 0) + $matrix->get(1, 1) + $matrix->get(1, 2), Tolerances::linearAlgebra());
    }

    public function testCollinearPrimariesAreRejected(): void
    {
        $primaries = new RgbPrimaries(new Chromaticity(0.2, 0.2), new Chromaticity(0.3, 0.3), new Chromaticity(0.4, 0.4));

        $this->expectException(SingularMatrixException::class);

        $primaries->toXyzMatrix(WhitePoints::d65());
    }

    public function testBradfordIdentityForTheSameWhite(): void
    {
        self::assertSame(Matrix3::identity()->toRows(), (new Bradford())->matrix(WhitePoints::d65(), WhitePoints::d65())->toRows());
    }

    public function testBradfordMapsSourceWhiteToDestinationWhite(): void
    {
        $bradford = new Bradford();

        self::assertVectorClose(WhitePoints::d50()->xyz, $bradford->matrix(WhitePoints::d65(), WhitePoints::d50())->transform(WhitePoints::d65()->xyz), Tolerances::linearAlgebra());
        self::assertVectorClose(WhitePoints::d65()->xyz, $bradford->matrix(WhitePoints::d50(), WhitePoints::d65())->transform(WhitePoints::d50()->xyz), Tolerances::linearAlgebra());
    }

    public function testBradfordRoundTripIsIdentity(): void
    {
        $bradford = new Bradford();
        $roundTrip = $bradford->matrix(WhitePoints::d50(), WhitePoints::d65())->multiply($bradford->matrix(WhitePoints::d65(), WhitePoints::d50()));

        self::assertMatrixClose(Matrix3::identity(), $roundTrip, Tolerances::linearAlgebra());
    }

    public function testBradfordIdentity(): void
    {
        $id = (new Bradford())->id();

        self::assertSame('bradford[linear]@r1', $id->identifier());
        self::assertNotSame([], $id->sources);
    }

    public function testBradfordCitesItsSources(): void
    {
        $titles = \array_map(static fn($source): string => $source->title, (new Bradford())->id()->sources);

        self::assertSame(['CSS Color Module Level 4', 'ICC.1 Image technology colour management — Architecture, profile format, and data structure', 'K. M. Lam: Metamerism and colour constancy (PhD thesis, University of Bradford)'], $titles);
    }

}
