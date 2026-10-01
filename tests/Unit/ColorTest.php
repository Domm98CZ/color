<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Notation\CssFormat;
use Domm98CZ\Color\Notation\Exception\ColorParseException;
use Domm98CZ\Color\Notation\Exception\OutOfGamutException;
use Domm98CZ\Color\Notation\Exception\UnresolvableCssColorException;
use Domm98CZ\Color\Space\ColorSpaceRegistry;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\ConversionOptions;
use Domm98CZ\Color\Space\Exception\InvalidColorValueException;
use Domm98CZ\Color\Space\Exception\UnknownChannelException;
use Domm98CZ\Color\Space\Record\Hsl;
use Domm98CZ\Color\Space\Record\Lab;
use Domm98CZ\Color\Space\Record\Oklch;
use Domm98CZ\Color\Space\Record\Rgb255;
use Domm98CZ\Color\Tests\Fake\FakeChromaticAdaptation;
use Domm98CZ\Color\Tests\Fake\PercentSrgbSpace;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ColorTest extends TestCase
{
    use FloatAssertions;

    public function testFromRgbIsExactSrgb(): void
    {
        $color = Color::fromRgb(51, 102, 204);

        self::assertSame(ColorSpaces::srgb(), $color->space());
        self::assertSame([51 / 255, 102 / 255, 204 / 255], $color->coordinates());
        self::assertSame(1.0, $color->alpha());
        self::assertTrue($color->equals(Color::fromSrgb(51 / 255, 102 / 255, 204 / 255)));
        self::assertSame(0.5, Color::fromRgba(51, 102, 204, 0.5)->alpha());
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function invalidRgb(): iterable
    {
        yield '256' => [256, 0, 0];
        yield 'negative' => [0, -1, 0];
        yield 'blue too large' => [0, 0, 1000];
    }

    #[DataProvider('invalidRgb')]
    public function testFromRgbRejectsOutOfRange(int $r, int $g, int $b): void
    {
        $this->expectException(InvalidColorValueException::class);

        Color::fromRgb($r, $g, $b);
    }

    public function testNamedConstructorsUseTheDocumentedSpacesAndScales(): void
    {
        self::assertSame('hsl', Color::fromHsl(220, 60, 50)->space()->id());
        self::assertSame([220.0, 60.0, 50.0], Color::fromHsl(220, 60, 50)->coordinates());
        self::assertSame('hwb', Color::fromHwb(150, 20, 10)->space()->id());
        self::assertSame('hsv', Color::fromHsv(220, 75, 80)->space()->id());
        self::assertSame('lab', Color::fromLab(50, 10, -20)->space()->id());
        self::assertSame('lch', Color::fromLch(50, 30, 120)->space()->id());
        self::assertSame('oklab', Color::fromOklab(0.5, 0.1, -0.1)->space()->id());
        self::assertSame('oklch', Color::fromOklch(0.7, 0.1, 250)->space()->id());
        self::assertSame('xyz-d65', Color::fromXyz(0.2, 0.3, 0.4)->space()->id());
        self::assertTrue(Color::fromHsl(220, 60, 50)->isCloseTo(Color::fromRgb(51, 102, 204), Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB)));
    }

    public function testOutOfGamutValuesAreKept(): void
    {
        $color = Color::fromSrgb(1.2, -0.1, 0.5);

        self::assertSame([1.2, -0.1, 0.5], $color->coordinates());
        self::assertFalse($color->isInGamut());
        self::assertTrue($color->isInGamut(ColorSpaces::rec2020()));
    }

    public function testHueIsNormalisedAndNegativeZeroRemoved(): void
    {
        $color = Color::fromOklch(0.5, 0.1, -30);

        self::assertSame(330.0, $color->coordinate('h'));
        self::assertPositiveZero(Color::fromSrgb(-0.0, 0.0, 0.0)->coordinates()[0] ?? \NAN);
        self::assertSame(0.0, Color::fromOklch(0.5, 0.1, 360)->coordinate('h'));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function invalidCoordinates(): iterable
    {
        yield 'too few' => [[0.1, 0.2]];
        yield 'too many' => [[0.1, 0.2, 0.3, 0.4]];
        yield 'NAN' => [[\NAN, 0.2, 0.3]];
        yield 'INF' => [[0.1, \INF, 0.3]];
        yield 'string' => [[0.1, '0.2', 0.3]];
        yield 'associative' => [['r' => 0.1, 'g' => 0.2, 'b' => 0.3]];
    }

    /**
     * @param array<array-key, mixed> $coordinates
     */
    #[DataProvider('invalidCoordinates')]
    public function testInvalidCoordinatesAreRejected(array $coordinates): void
    {
        $this->expectException(InvalidColorValueException::class);

        Color::of(ColorSpaces::srgb(), $coordinates);
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function invalidAlpha(): iterable
    {
        yield 'above one' => [1.5];
        yield 'negative' => [-0.1];
        yield 'NAN' => [\NAN];
    }

    #[DataProvider('invalidAlpha')]
    public function testInvalidAlphaIsRejected(float $alpha): void
    {
        $this->expectException(InvalidColorValueException::class);

        Color::fromRgb(0, 0, 0)->withAlpha($alpha);
    }

    public function testMissingComponents(): void
    {
        $color = Color::of(ColorSpaces::oklch(), [0.7, null, 120], null);

        self::assertTrue($color->hasMissingComponents());
        self::assertTrue($color->isAlphaMissing());
        self::assertSame(0.0, $color->alpha(), 'A missing alpha behaves as zero (CSS Color 4 § 4.4).');
        self::assertNull($color->coordinate('c'));
        self::assertTrue($color->to($color->space())->equals($color), 'Converting to the same space keeps missing components.');
        self::assertTrue($color->to(ColorSpaces::srgb())->isCloseTo(Color::of(ColorSpaces::oklch(), [0.7, 0.0, 120], 0.0), Tolerances::linearAlgebra()));
        self::assertFalse(Color::fromRgb(1, 2, 3)->hasMissingComponents());
    }

    public function testConversionKeepsAlphaAndSetsPowerlessHue(): void
    {
        $gray = Color::fromRgba(128, 128, 128, 0.25)->to(ColorSpaces::oklch());

        self::assertNull($gray->coordinate('h'));
        self::assertSame(0.0, $gray->coordinate('c'));
        self::assertSame(0.25, $gray->alpha());
    }

    public function testTypedRecordAccess(): void
    {
        $color = Color::fromRgb(51, 102, 204);

        self::assertFloatClose(220.0, Color::fromRgb(51, 102, 204)->in(Hsl::class)->h ?? \NAN, Tolerances::linearAlgebra());
        self::assertFloatClose(0.5324825621443728, $color->in(Oklch::class)->l, Tolerances::linearAlgebra());
        self::assertFloatClose(102.0, $color->in(Rgb255::class)->g, Tolerances::linearAlgebra());
        self::assertNull(Color::fromRgb(0, 0, 0)->in(Oklch::class)->h);
        self::assertTrue(Color::from($color->in(Lab::class))->isCloseTo($color, Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB)));
    }

    public function testInHonoursConversionOptions(): void
    {
        $color = Color::fromRgb(51, 102, 204);

        self::assertNotEquals($color->in(Lab::class)->a, $color->in(Lab::class, new ConversionOptions(new FakeChromaticAdaptation()))->a);
    }

    public function testGamutChecksUseTolerance(): void
    {
        $roundTrip = Color::fromRgb(255, 0, 0)->to(ColorSpaces::oklch())->to(ColorSpaces::srgb());

        self::assertTrue($roundTrip->isInGamut(), 'Round-trip noise stays within the default tolerance.');
        self::assertFalse(Color::fromSrgb(1.00001, 0.0, 0.0)->isInGamut());
        self::assertTrue(Color::fromSrgb(1.00001, 0.0, 0.0)->isInGamut(null, Tolerance::absolute(1e-4)));
        self::assertTrue(Color::fromLab(150, 300, -300)->isInGamut(), 'Lab has no gamut boundary.');
        self::assertFalse(Color::fromLab(50, 100, 0)->isInGamut(ColorSpaces::srgb()));
        self::assertTrue(Color::fromRgb(255, 255, 255)->isInGamut(ColorSpaces::hsl()));
    }

    public function testDisplayP3RedIsOutsideSrgb(): void
    {
        $red = Color::of(ColorSpaces::displayP3(), [1, 0, 0]);

        self::assertTrue($red->isInGamut());
        self::assertFalse($red->isInGamut(ColorSpaces::srgb()));
        self::assertTrue(Color::fromRgb(255, 0, 0)->isInGamut(ColorSpaces::displayP3()));
    }

    public function testLowLevelChanges(): void
    {
        $color = Color::fromOklch(0.5, 0.1, 250);

        self::assertSame([0.7, 0.1, 250.0], $color->withCoordinate('l', 0.7)->coordinates());
        self::assertSame([0.5, 0.1, 10.0], $color->withCoordinate(2, 370)->coordinates());
        self::assertSame([0.5, 0.1, 250.0], $color->coordinates(), 'Original is unchanged.');
        self::assertSame(0.3, $color->withAlpha(0.3)->alpha());
        self::assertTrue($color->withAlpha(null)->isAlphaMissing());
        self::assertSame(1.0, $color->withAlpha(0.3)->opaque()->alpha());
    }

    public function testUnknownChannel(): void
    {
        $this->expectException(UnknownChannelException::class);
        $this->expectExceptionMessage('"oklch" has no channel "x"');

        Color::fromOklch(0.5, 0.1, 250)->coordinate('x');
    }

    public function testUnknownChannelIndex(): void
    {
        $this->expectException(UnknownChannelException::class);

        Color::fromOklch(0.5, 0.1, 250)->withCoordinate(3, 1.0);
    }

    public function testEqualityIsExact(): void
    {
        $a = Color::fromRgb(51, 102, 204);

        self::assertTrue($a->equals(Color::fromRgb(51, 102, 204)));
        self::assertFalse($a->equals(Color::fromRgba(51, 102, 204, 0.99)));
        self::assertFalse($a->equals($a->to(ColorSpaces::oklab())), 'Different spaces are never equal.');
        self::assertFalse($a->equals($a->to(ColorSpaces::oklab())->to(ColorSpaces::srgb())), 'Round-trip noise breaks exact equality.');
    }

    public function testIsCloseToConvertsAndComparesHueCyclically(): void
    {
        $a = Color::fromOklch(0.6, 0.1, 359.9999999999);
        $b = Color::fromOklch(0.6, 0.1, 0.0000000001);

        self::assertTrue($a->isCloseTo($b, Tolerance::absolute(1e-9)), 'hue 2e-10° apart across 0°'); // parameter under test
        self::assertTrue(Color::fromRgb(51, 102, 204)->isCloseTo(Color::fromRgb(51, 102, 204)->to(ColorSpaces::lab()), Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB)));
        self::assertFalse(Color::fromOklch(0.6, 0.1, 10)->isCloseTo(Color::fromOklch(0.6, 0.1, 11), Tolerance::absolute(0.5)));
        self::assertTrue(Color::of(ColorSpaces::oklch(), [0.6, 0.0, null])->isCloseTo(Color::fromOklch(0.6, 0.0, 200), Tolerances::linearAlgebra()), 'A missing hue is ignored.');
        self::assertFalse(Color::fromRgb(1, 2, 3)->isCloseTo(Color::fromRgba(1, 2, 3, 0.5), Tolerance::absolute(0.1)), 'Alpha is compared.');
    }

    public function testUserDefinedSpace(): void
    {
        $color = Color::of(new PercentSrgbSpace(), [20, 40, 80]);

        self::assertTrue($color->isCloseTo(Color::fromRgb(51, 102, 204), Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB)));
        self::assertTrue($color->isInGamut());
        self::assertFalse(Color::of(new PercentSrgbSpace(), [120, 40, 80])->isInGamut());
        self::assertSame(20.0, $color->coordinate('r'));
    }

    public function testNamedConstructorsDefaultToOpaque(): void
    {
        foreach ([
            Color::fromSrgb(0.1, 0.2, 0.3),
            Color::fromHsl(10, 20, 30),
            Color::fromHwb(10, 20, 30),
            Color::fromHsv(10, 20, 30),
            Color::fromLab(10, 20, 30),
            Color::fromLch(10, 20, 30),
            Color::fromOklab(0.1, 0.2, 0.3),
            Color::fromOklch(0.1, 0.2, 30),
            Color::fromXyz(0.1, 0.2, 0.3),
            Color::of(ColorSpaces::srgb(), [0.1, 0.2, 0.3]),
        ] as $color) {
            self::assertSame(1.0, $color->alpha(), $color->space()->id());
            self::assertFalse($color->isAlphaMissing());
        }
    }

    public function testHasMissingComponentsDetectsEachKind(): void
    {
        self::assertTrue(Color::of(ColorSpaces::srgb(), [0.1, 0.2, 0.3], null)->hasMissingComponents(), 'missing alpha only');
        self::assertTrue(Color::of(ColorSpaces::srgb(), [0.1, null, 0.3])->hasMissingComponents(), 'missing coordinate only');
    }

    public function testConvertingToTheOwnSpaceReturnsTheSameInstance(): void
    {
        $color = Color::fromRgb(1, 2, 3);

        self::assertSame($color, $color->to(ColorSpaces::srgb()));
    }

    public function testGamutBoundaryIsInclusive(): void
    {
        $exact = \Domm98CZ\Color\Math\Tolerance::exact();

        self::assertTrue(Color::fromSrgb(1.0, 0.0, 0.5)->isInGamut(null, $exact));
        self::assertFalse(Color::fromSrgb(1.0 + 1e-15, 0.0, 0.5)->isInGamut(null, $exact));
        self::assertFalse(Color::fromSrgb(-1e-15, 0.0, 0.5)->isInGamut(null, $exact));
        self::assertTrue(Color::fromSrgb(1.0 + 1e-6, -1e-6, 0.5)->isInGamut(), 'The default tolerance 1e-6 is inclusive.');
    }

    public function testIsCloseToBoundariesAndMissingComponents(): void
    {
        self::assertTrue(Color::fromOklch(0.6, 0.1, 10)->isCloseTo(Color::fromOklch(0.6, 0.1, 11), Tolerance::absolute(1.0)), 'A hue difference equal to the tolerance is close.');
        self::assertTrue(Color::of(ColorSpaces::oklch(), [0.6, null, 120])->isCloseTo(Color::fromOklch(0.6, 0.0, 120), Tolerance::exact()), 'Missing chroma compares as zero.');
        self::assertTrue(Color::fromOklch(0.6, 0.0, 120)->isCloseTo(Color::of(ColorSpaces::oklch(), [0.6, null, 120]), Tolerance::exact()));
        self::assertFalse(Color::fromHsl(359.9, 20, 50)->isCloseTo(Color::fromHsl(0.1, 90, 50), Tolerance::absolute(0.5)), 'Channels after a close hue are still compared.');
        self::assertTrue(Color::fromHsl(359.9, 20, 50)->isCloseTo(Color::fromHsl(0.1, 20, 50), Tolerance::absolute(0.5)));
    }

    public function testUnknownChannelMessageForIndex(): void
    {
        $this->expectException(UnknownChannelException::class);
        $this->expectExceptionMessage('Color space "srgb" has no channel #5.');

        Color::fromRgb(0, 0, 0)->coordinate(5);
    }

    public function testInvalidCoordinateCountMessage(): void
    {
        $this->expectException(InvalidColorValueException::class);
        $this->expectExceptionMessage('Color space "srgb" expects a list of 3 coordinates, 2 given.');

        Color::of(ColorSpaces::srgb(), [0.1, 0.2]);
    }

    public function testNonListCoordinatesMessage(): void
    {
        $this->expectException(InvalidColorValueException::class);
        $this->expectExceptionMessage('expects a list of 3 coordinates, a non-list array given.');

        Color::of(ColorSpaces::srgb(), [1 => 0.1, 2 => 0.2, 3 => 0.3]);
    }

    public function testNotationConstructors(): void
    {
        self::assertTrue(Color::fromHex('#3366cc')->equals(Color::fromRgb(51, 102, 204)));
        self::assertTrue(Color::fromHex('36c8')->equals(Color::fromRgba(51, 102, 204, 136 / 255)));
        self::assertTrue(Color::parse('rgb(51 102 204)')->equals(Color::fromRgb(51, 102, 204)));
        self::assertSame('hsl', Color::parse('hsl(220 60% 50%)')->space()->id());
        self::assertSame([180.0, 10.0, 20.0], Color::parse('hsl(-540 10% 20%)')->coordinates(), 'Color normalises the hue');
        self::assertTrue(Color::parse('oklch(0.5 0.1 none / none)')->isAlphaMissing());

        $json = '{"v":1,"space":"test-percent-srgb","coords":[10,20,30],"alpha":1}';
        self::assertSame([10.0, 20.0, 30.0], Color::fromJson($json, ColorSpaceRegistry::default()->with(new PercentSrgbSpace()))->coordinates());
        self::assertTrue(Color::fromJson('{"v":1,"space":"lab","coords":[50,10,-10],"alpha":0.5}')->equals(Color::fromLab(50, 10, -10, 0.5)));
    }

    public function testFromHexReportsErrors(): void
    {
        $this->expectException(ColorParseException::class);
        $this->expectExceptionMessage('Cannot parse color "#12345"');

        Color::fromHex('#12345');
    }

    public function testParseReportsErrors(): void
    {
        $this->expectException(ColorParseException::class);
        $this->expectExceptionMessage('Cannot parse color "rgb(1 2)"');

        Color::parse('rgb(1 2)');
    }

    public function testParseReportsUnresolvableColors(): void
    {
        $this->expectException(UnresolvableCssColorException::class);

        Color::parse('currentcolor');
    }

    public function testSerialisation(): void
    {
        $color = Color::fromRgba(51, 102, 204, 0.5);

        self::assertSame('#3366cc80', $color->toHex());
        self::assertSame('#3366ccff', $color->opaque()->toHex(true));
        self::assertSame('#3366cc', $color->opaque()->toHex());
        self::assertSame('rgba(51, 102, 204, 0.5)', $color->toCss());
        self::assertSame('oklch(0.5 0.1 250)', Color::fromOklch(0.5, 0.1, 250)->toCss());
        self::assertSame('lab(50.12 0 0)', Color::fromLab(50.123456, 0, 0)->toCss(new CssFormat(2)));
        self::assertSame('{"v":1,"space":"srgb","coords":[0.2,0.4,0.8],"alpha":0.5}', $color->toJson());
        self::assertSame('{"v":1,"space":"oklch","coords":[0.5,0,null],"alpha":null}', Color::of(ColorSpaces::oklch(), [0.5, 0.0, null], null)->toJson());
    }

    public function testToHexRefusesOutOfGamutColors(): void
    {
        $p3 = Color::of(ColorSpaces::displayP3(), [1.0, 0.0, 0.0]);

        self::assertSame('color(display-p3 1 0 0)', $p3->toCss(), 'CSS keeps the color');

        try {
            $p3->toHex();
            self::fail('Must not clip.');
        } catch (OutOfGamutException $exception) {
            self::assertSame('display-p3', $exception->getSpace()->id());
            self::assertSame([1.0, 0.0, 0.0], $exception->getCoordinates());
        }
    }
}
