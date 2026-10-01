<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Space;

use Domm98CZ\Color\Colorimetry\Algorithm\Bradford;
use Domm98CZ\Color\Colorimetry\Algorithm\SrgbTransferFunction;
use Domm98CZ\Color\Colorimetry\WhitePoints;
use Domm98CZ\Color\Math\Vector3;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\ConversionOptions;
use Domm98CZ\Color\Space\Converter;
use Domm98CZ\Color\Tests\Fake\FakeChromaticAdaptation;
use Domm98CZ\Color\Tests\Fake\PercentSrgbSpace;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\TestCase;

final class ConverterTest extends TestCase
{
    use FloatAssertions;

    public function testSameSpaceIsIdentityIncludingMissingComponents(): void
    {
        $coordinates = [0.5, null, 120.0];

        self::assertSame($coordinates, (new Converter())->convert(ColorSpaces::oklch(), ColorSpaces::oklch(), $coordinates));
    }

    public function testMissingComponentsBehaveAsZero(): void
    {
        $converter = new Converter();

        self::assertSame(
            $converter->convert(ColorSpaces::srgb(), ColorSpaces::srgbLinear(), [0.0, 0.5, 0.5]),
            $converter->convert(ColorSpaces::srgb(), ColorSpaces::srgbLinear(), [null, 0.5, 0.5]),
        );
    }

    public function testShortestPathThroughCommonAncestor(): void
    {
        $adaptation = new FakeChromaticAdaptation();
        $converter = new Converter(new ConversionOptions($adaptation));

        $lch = $converter->convert(ColorSpaces::oklab(), ColorSpaces::oklch(), [0.5, 0.03, 0.04]);
        $back = $converter->convert(ColorSpaces::oklch(), ColorSpaces::oklab(), $lch);

        self::assertNotNull($lch[1]);
        self::assertFloatClose(0.05, $lch[1], Tolerances::linearAlgebra());
        self::assertSame([], $adaptation->calls, 'No chromatic adaptation within one tree.');
        self::assertNotNull($back[1]);
        self::assertFloatClose(0.03, $back[1], Tolerances::linearAlgebra());
    }

    public function testAdaptationIsAppliedOnlyBetweenDifferentWhites(): void
    {
        $adaptation = new FakeChromaticAdaptation();
        $converter = new Converter(new ConversionOptions($adaptation));

        $converter->convert(ColorSpaces::srgb(), ColorSpaces::oklch(), [0.2, 0.4, 0.8]);
        self::assertSame([], $adaptation->calls);

        $converter->convert(ColorSpaces::srgb(), ColorSpaces::lab(), [0.2, 0.4, 0.8]);
        self::assertSame([['D65', 'D50']], $adaptation->calls);
    }

    public function testDefaultAdaptationIsLinearBradford(): void
    {
        $viaDefault = (new Converter())->convert(ColorSpaces::xyzD65(), ColorSpaces::xyzD50(), [0.3, 0.4, 0.5]);
        $expected = (new Bradford())->matrix(WhitePoints::d65(), WhitePoints::d50())->transform(new Vector3(0.3, 0.4, 0.5));

        self::assertSame($expected->toList(), $viaDefault);
    }

    public function testChosenAdaptationChangesTheResult(): void
    {
        $bradford = (new Converter())->convert(ColorSpaces::srgb(), ColorSpaces::lab(), [0.2, 0.4, 0.8]);
        $identity = (new Converter(new ConversionOptions(new FakeChromaticAdaptation())))->convert(ColorSpaces::srgb(), ColorSpaces::lab(), [0.2, 0.4, 0.8]);

        self::assertNotEquals($bradford, $identity);
    }

    public function testUserDefinedSpaceTakesPartInConversions(): void
    {
        $converter = new Converter();
        $percent = new PercentSrgbSpace();

        self::assertSame([20.0, 40.0, 80.0], $converter->convert(ColorSpaces::srgb(), $percent, [0.2, 0.4, 0.8]));

        $viaPercent = $converter->convert($percent, ColorSpaces::oklab(), [20.0, 40.0, 80.0]);
        $direct = $converter->convert(ColorSpaces::srgb(), ColorSpaces::oklab(), [0.2, 0.4, 0.8]);

        foreach ([0, 1, 2] as $index) {
            self::assertNotNull($viaPercent[$index]);
            self::assertNotNull($direct[$index]);
            self::assertFloatClose($direct[$index], $viaPercent[$index], Tolerances::linearAlgebra());
        }
    }

    public function testConversionNeverClips(): void
    {
        $srgb = (new Converter())->convert(ColorSpaces::displayP3(), ColorSpaces::srgb(), [1.0, 0.0, 0.0]);

        self::assertNotNull($srgb[0]);
        self::assertNotNull($srgb[1]);
        self::assertGreaterThan(1.0, $srgb[0]);
        self::assertLessThan(0.0, $srgb[1]);
    }

    public function testRgbSpaceFromPrimariesBuildsALinearBase(): void
    {
        $space = \Domm98CZ\Color\Space\Definition\RgbSpace::fromPrimaries(
            'test-srgb-copy',
            'sRGB copy',
            ColorSpaces::srgbLinear()->primaries,
            ColorSpaces::xyzD65(),
            new SrgbTransferFunction(),
        );

        self::assertSame('test-srgb-copy-linear', $space->base()->id());
        self::assertSame('sRGB copy (linear)', $space->base()->name());

        $copy = (new Converter())->convert($space, ColorSpaces::srgb(), [0.2, 0.4, 0.8]);
        foreach ([0.2, 0.4, 0.8] as $index => $expected) {
            self::assertNotNull($copy[$index]);
            self::assertFloatClose($expected, $copy[$index], Tolerances::linearAlgebra());
        }
    }
}
