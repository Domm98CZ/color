<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Colorimetry;

use Domm98CZ\Color\Colorimetry\Chromaticity;
use Domm98CZ\Color\Colorimetry\Exception\InvalidColorimetricDataException;
use Domm98CZ\Color\Colorimetry\StandardObserver;
use Domm98CZ\Color\Colorimetry\WhitePoint;
use Domm98CZ\Color\Colorimetry\WhitePoints;
use Domm98CZ\Color\Math\Exception\NonFiniteValueException;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Math\Vector3;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\TestCase;

final class ChromaticityAndWhitePointTest extends TestCase
{
    use FloatAssertions;

    public function testChromaticityToXyzAndBack(): void
    {
        $xy = new Chromaticity(0.3127, 0.3290);
        $xyz = $xy->toXyz(1.0);

        self::assertSame(0.3127 / 0.3290, $xyz->x);
        self::assertSame(1.0, $xyz->y);
        self::assertSame((1.0 - 0.3127 - 0.3290) / 0.3290, $xyz->z);
        self::assertSame(2.0, $xy->toXyz(2.0)->y);

        $back = Chromaticity::fromXyz($xyz);
        self::assertFloatClose(0.3127, $back->x, Tolerances::linearAlgebra());
        self::assertFloatClose(0.3290, $back->y, Tolerances::linearAlgebra());
    }

    public function testChromaticityRejectsNonPositiveY(): void
    {
        $this->expectException(InvalidColorimetricDataException::class);

        new Chromaticity(0.3, 0.0);
    }

    public function testChromaticityRejectsNonFinite(): void
    {
        $this->expectException(NonFiniteValueException::class);

        new Chromaticity(\NAN, 0.3);
    }

    public function testChromaticityOfBlackIsUndefined(): void
    {
        $this->expectException(InvalidColorimetricDataException::class);

        Chromaticity::fromXyz(Vector3::zero());
    }

    public function testCssWhitePoints(): void
    {
        $d65 = WhitePoints::d65();
        $d50 = WhitePoints::d50();

        // Exactly the CSS Color 4 definition: [x/y, 1, (1 − x − y)/y].
        self::assertSame([0.3127 / 0.3290, 1.0, (1.0 - 0.3127 - 0.3290) / 0.3290], $d65->xyz->toList());
        self::assertSame([0.3457 / 0.3585, 1.0, (1.0 - 0.3457 - 0.3585) / 0.3585], $d50->xyz->toList());
        self::assertSame('D65', $d65->name);
        self::assertSame(StandardObserver::Cie1931TwoDegree, $d65->observer);
        self::assertSame($d65, WhitePoints::d65(), 'Built-in white points are shared instances.');
        self::assertFloatClose(0.3457, $d50->chromaticity()->x, Tolerances::linearAlgebra());
    }

    public function testWhitePointEquality(): void
    {
        $d65 = WhitePoints::d65();

        self::assertTrue($d65->equals(WhitePoint::fromChromaticity('another name', new Chromaticity(0.3127, 0.3290))));
        self::assertFalse($d65->equals(WhitePoints::d50()));
        self::assertFalse($d65->equals(WhitePoint::fromChromaticity('D65', new Chromaticity(0.31272, 0.32903))), 'Differently rounded D65 is a different white.');
        self::assertFalse($d65->equals(WhitePoint::fromXyz('D65/10', $d65->xyz, StandardObserver::Cie1964TenDegree)));
    }

    public function testWhitePointFromXyzMustBeNormalised(): void
    {
        $this->expectException(InvalidColorimetricDataException::class);

        WhitePoint::fromXyz('ICC PCS D50 unnormalised', new Vector3(96.42, 100.0, 82.49));
    }

    public function testWhitePointRejectsNonPositiveXz(): void
    {
        $this->expectException(InvalidColorimetricDataException::class);

        WhitePoint::fromXyz('broken', new Vector3(-0.1, 1.0, 1.0));
    }

    public function testWhitePointRejectsEmptyName(): void
    {
        $this->expectException(InvalidColorimetricDataException::class);

        WhitePoint::fromXyz(' ', new Vector3(0.9642, 1.0, 0.8249));
    }

    public function testIccPcsD50CanBeDefinedSeparately(): void
    {
        $icc = WhitePoint::fromXyz('ICC PCS D50', new Vector3(0.9642, 1.0, 0.8249));

        self::assertFalse($icc->equals(WhitePoints::d50()));
        self::assertFloatClose(WhitePoints::d50()->xyz->x, $icc->xyz->x, Tolerance::absolute(2 * Tolerances::printedDecimals(4)));
    }

    public function testToXyzDefaultsToUnitLuminanceAndScalesLinearly(): void
    {
        $xy = new Chromaticity(0.3, 0.4);

        self::assertSame($xy->toXyz(1.0)->toList(), $xy->toXyz()->toList());
        self::assertSame([0.3 * 2.0 / 0.4, 2.0, (1.0 - 0.3 - 0.4) * 2.0 / 0.4], $xy->toXyz(2.0)->toList());
    }

    public function testWhitePointRejectsZeroX(): void
    {
        $this->expectException(InvalidColorimetricDataException::class);

        WhitePoint::fromXyz('zero X', new Vector3(0.0, 1.0, 1.0));
    }

    public function testWhitePointRejectsZeroZ(): void
    {
        $this->expectException(InvalidColorimetricDataException::class);

        WhitePoint::fromXyz('zero Z', new Vector3(1.0, 1.0, 0.0));
    }

    public function testBuiltInD50IsShared(): void
    {
        self::assertSame(WhitePoints::d50(), WhitePoints::d50());
    }

}
