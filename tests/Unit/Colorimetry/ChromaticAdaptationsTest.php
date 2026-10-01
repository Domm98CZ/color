<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Colorimetry;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Colorimetry\ChromaticAdaptations;
use Domm98CZ\Color\Colorimetry\Internal\ConeSpaceScaling;
use Domm98CZ\Color\Colorimetry\WhitePoint;
use Domm98CZ\Color\Colorimetry\WhitePoints;
use Domm98CZ\Color\Math\Matrix3;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Math\Vector3;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\ConversionOptions;
use Domm98CZ\Color\Tests\Fake\FakeChromaticAdaptation;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\TestCase;

final class ChromaticAdaptationsTest extends TestCase
{
    use FloatAssertions;

    public function testWhitesMapToWhites(): void
    {
        $transform = ChromaticAdaptations::bradford();
        self::assertSame('bradford[linear]@r1', $transform->id()->identifier());
        self::assertSame('CSS Color Module Level 4', $transform->id()->sources[0]->title);

        $whites = [WhitePoints::d65(), WhitePoints::d50()];
        foreach ($whites as $from) {
            self::assertSame(Matrix3::identity()->toRows(), $transform->matrix($from, $from)->toRows());
            foreach ($whites as $to) {
                $matrix = $transform->matrix($from, $to);
                self::assertVectorClose($to->xyz, $matrix->transform($from->xyz), Tolerances::linearAlgebra(), $from->name . ' → ' . $to->name);
                self::assertMatrixClose(Matrix3::identity(), $transform->matrix($to, $from)->multiply($matrix), Tolerances::linearAlgebra());
            }
        }
    }

    public function testConversionsUseTheChosenTransform(): void
    {
        $color = Color::fromSrgb(0.8, 0.3, 0.2);
        $fake = new FakeChromaticAdaptation();
        $options = new ConversionOptions($fake);
        $xyzD65 = \array_map(static fn(?float $v): float => $v ?? 0.0, $color->to(ColorSpaces::xyzD65())->coordinates());

        $viaFake = $color->to(ColorSpaces::xyzD50(), $options)->coordinates();
        self::assertSame([['D65', 'D50']], $fake->calls);
        self::assertFloatClose($xyzD65[0], $viaFake[0] ?? \NAN, Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB), 'the identity fake leaves XYZ unchanged');
        self::assertFalse($color->to(ColorSpaces::xyzD50())->isCloseTo($color->to(ColorSpaces::xyzD50(), $options), Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB)), 'Bradford by default');
    }

    public function testAdaptationMatricesAreMemoizedPerWhites(): void
    {
        ConeSpaceScaling::clear();
        $bradford = ChromaticAdaptations::bradford();
        $forward = $bradford->matrix(WhitePoints::d65(), WhitePoints::d50());
        self::assertSame($forward, $bradford->matrix(WhitePoints::d65(), WhitePoints::d50()), 'the cached matrix is returned');
        self::assertSame($forward, ChromaticAdaptations::bradford()->matrix(WhitePoint::fromXyz('copy', WhitePoints::d65()->xyz), WhitePoints::d50()), 'keyed by the white, not its name');
        self::assertFalse($forward->isCloseTo($bradford->matrix(WhitePoints::d50(), WhitePoints::d65()), Tolerance::absolute(1e-3)), 'the direction is part of the key');
        self::assertTrue($forward->multiply($bradford->matrix(WhitePoints::d50(), WhitePoints::d65()))->isCloseTo(Matrix3::identity(), Tolerance::absolute(1e-15)));
        $backward = $bradford->matrix(WhitePoints::d50(), WhitePoints::d65());
        self::assertSame($forward, $bradford->matrix(WhitePoints::d65(), WhitePoints::d50()), 'several pairs stay cached');
        self::assertSame($backward, $bradford->matrix(WhitePoints::d50(), WhitePoints::d65()));
    }

    public function testTheMemoIsBounded(): void
    {
        ConeSpaceScaling::clear();
        self::assertSame(0, ConeSpaceScaling::cached());
        for ($i = 1; $i <= 2 * ConeSpaceScaling::CACHE_SIZE + 1; $i++) {
            $white = WhitePoint::fromXyz('test ' . $i, new Vector3(0.9 + $i / 1000, 1.0, 1.0));
            $matrix = ChromaticAdaptations::bradford()->matrix($white, WhitePoints::d65());
            self::assertSame($matrix, ChromaticAdaptations::bradford()->matrix($white, WhitePoints::d65()));
            self::assertSame(($i - 1) % ConeSpaceScaling::CACHE_SIZE + 1, ConeSpaceScaling::cached(), 'full memo is emptied, then refilled');
        }
        self::assertTrue(ChromaticAdaptations::bradford()->matrix(WhitePoints::d65(), WhitePoints::d65())->isCloseTo(Matrix3::identity(), Tolerance::exact()));
        self::assertSame(1, ConeSpaceScaling::cached(), 'the identity for equal whites is not memoized');
    }
}
