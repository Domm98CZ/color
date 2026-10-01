<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Space\Definition;

use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Definition\HslSpace;
use Domm98CZ\Color\Space\Definition\HsvSpace;
use Domm98CZ\Color\Space\Definition\HwbSpace;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * HSL, HWB and HSV over sRGB (CSS Color 4 §§ 7–8; HSV per Smith 1978 via its relation to HWB).
 */
final class CylindricalRgbSpacesTest extends TestCase
{
    use FloatAssertions;

    /**
     * @param list<float|null> $expected
     * @param list<float|null> $actual
     */
    private static function assertCoordinates(array $expected, array $actual, Tolerance $tolerance): void
    {
        self::assertCount(\count($expected), $actual);

        foreach ($expected as $index => $value) {
            if ($value === null) {
                self::assertNull($actual[$index], 'channel ' . $index);
                continue;
            }

            self::assertNotNull($actual[$index], 'channel ' . $index);
            self::assertFloatClose($value, $actual[$index], $tolerance, 'channel ' . $index);
        }
    }

    /**
     * @return iterable<string, array{list<float>, list<float|null>}>
     */
    public static function hslValues(): iterable
    {
        yield 'red' => [[1.0, 0.0, 0.0], [0.0, 100.0, 50.0]];
        yield 'lime' => [[0.0, 1.0, 0.0], [120.0, 100.0, 50.0]];
        yield 'blue' => [[0.0, 0.0, 1.0], [240.0, 100.0, 50.0]];
        yield '#3366cc' => [[0.2, 0.4, 0.8], [220.0, 60.0, 50.0]];
        yield 'white' => [[1.0, 1.0, 1.0], [null, 0.0, 100.0]];
        yield 'black' => [[0.0, 0.0, 0.0], [null, 0.0, 0.0]];
        yield 'gray' => [[0.5, 0.5, 0.5], [null, 0.0, 50.0]];
    }

    /**
     * @param list<float> $rgb
     * @param list<float|null> $hsl
     */
    #[DataProvider('hslValues')]
    public function testHslKnownValues(array $rgb, array $hsl): void
    {
        self::assertCoordinates($hsl, ColorSpaces::hsl()->fromBase($rgb), Tolerances::linearAlgebra());
        self::assertCoordinates($rgb, ColorSpaces::hsl()->toBase(\array_map(static fn(?float $v): float => $v ?? 0.0, $hsl)), Tolerances::linearAlgebra());
    }

    public function testHslPowerlessHueSetsTinySaturationToZero(): void
    {
        // S ≈ 0.0002 (≤ ε = 0.001): hue powerless, saturation set to 0 (CSS Color 4 § 4.4.1).
        $hsl = ColorSpaces::hsl()->fromBase([0.5, 0.5, 0.5 + 2e-6]);

        self::assertNull($hsl[0]);
        self::assertSame(0.0, $hsl[1]);

        $above = ColorSpaces::hsl()->fromBase([0.5, 0.5, 0.5 + 2e-5]);
        self::assertNotNull($above[0]);
        self::assertGreaterThan(HslSpace::POWERLESS_EPSILON, $above[1]);
    }

    public function testHslNegativeSaturationIsRotated(): void
    {
        // Far out of gamut: the raw formula yields negative saturation; CSS rotates the hue by 180°.
        $hsl = ColorSpaces::hsl()->fromBase([-0.5, 1.2, 1.4]);

        self::assertNotNull($hsl[1]);
        self::assertGreaterThan(0.0, $hsl[1]);
    }

    public function testCssSpecificationExampleHwbHslRgb(): void
    {
        // CSS Color 4 § 8: "hwb(150 20% 10%) is the same color as hsl(150 77.78% 55%) and rgb(20% 90% 55%)".
        $rgb = ColorSpaces::hwb()->toBase([150.0, 20.0, 10.0]);
        self::assertCoordinates([0.2, 0.9, 0.55], $rgb, Tolerances::linearAlgebra());

        $hsl = ColorSpaces::hsl()->fromBase($rgb);
        self::assertCoordinates([150.0, 77.78, 55.0], $hsl, Tolerance::absolute(Tolerances::printedDecimals(2)));
    }

    public function testCssSpecificationExampleAchromaticHwb(): void
    {
        // CSS Color 4 § 8: hwb(45 40% 80%) is achromatic, R = G = B = 40 / (40 + 80).
        self::assertCoordinates([1 / 3, 1 / 3, 1 / 3], ColorSpaces::hwb()->toBase([45.0, 40.0, 80.0]), Tolerances::linearAlgebra());
    }

    public function testHwbPowerlessRuleNormalisesBlackness(): void
    {
        // W + B = 99.9995 ∈ [99.999, 100): hue powerless and B set to 100 − W (CSS Color 4 § 4.4.1).
        $hwb = ColorSpaces::hwb()->fromBase([0.5, 0.500005, 0.5]);

        self::assertNull($hwb[0]);
        self::assertNotNull($hwb[1]);
        self::assertNotNull($hwb[2]);
        self::assertSame(100.0 - $hwb[1], $hwb[2]);
        self::assertGreaterThanOrEqual(HwbSpace::POWERLESS_THRESHOLD, $hwb[1] + $hwb[2]);
    }

    public function testHwbChromaticColor(): void
    {
        self::assertCoordinates([220.0, 20.0, 20.0], ColorSpaces::hwb()->fromBase([0.2, 0.4, 0.8]), Tolerances::linearAlgebra());
    }

    /**
     * @return iterable<string, array{list<float>, list<float|null>}>
     */
    public static function hsvValues(): iterable
    {
        yield 'red' => [[1.0, 0.0, 0.0], [0.0, 100.0, 100.0]];
        yield '#3366cc' => [[0.2, 0.4, 0.8], [220.0, 75.0, 80.0]];
        yield 'dark cyan' => [[0.0, 0.5, 0.5], [180.0, 100.0, 50.0]];
        yield 'white' => [[1.0, 1.0, 1.0], [null, 0.0, 100.0]];
        yield 'black' => [[0.0, 0.0, 0.0], [null, 0.0, 0.0]];
    }

    /**
     * @param list<float> $rgb
     * @param list<float|null> $hsv
     */
    #[DataProvider('hsvValues')]
    public function testHsvKnownValues(array $rgb, array $hsv): void
    {
        self::assertCoordinates($hsv, ColorSpaces::hsv()->fromBase($rgb), Tolerances::linearAlgebra());
        self::assertCoordinates($rgb, ColorSpaces::hsv()->toBase(\array_map(static fn(?float $v): float => $v ?? 0.0, $hsv)), Tolerances::linearAlgebra());
    }

    public function testHsvPowerlessHueSetsTinySaturationToZero(): void
    {
        $hsv = ColorSpaces::hsv()->fromBase([0.5, 0.5, 0.5 + 2e-6]);

        self::assertNull($hsv[0]);
        self::assertSame(0.0, $hsv[1]);
    }

    public function testHsvKeepsTheHueOfOutOfGamutColorsWithNegativeValue(): void
    {
        // max < 0 makes S negative; the hue is still defined because the chroma is not small.
        $rgb = [-0.49674658849683895, -0.08665709737066973, -0.0062561871047393525];
        $hsv = ColorSpaces::hsv()->fromBase($rgb);

        self::assertNotNull($hsv[0]);
        self::assertCoordinates($rgb, ColorSpaces::hsv()->toBase(\array_map(static fn(?float $v): float => $v ?? 0.0, $hsv)), Tolerances::linearAlgebra());
    }

    public function testIdsOverOtherRgbSpaces(): void
    {
        self::assertSame('hsl@display-p3', (new HslSpace(ColorSpaces::displayP3()))->id());
        self::assertSame('hwb@display-p3', (new HwbSpace(ColorSpaces::displayP3()))->id());
        self::assertSame('hsv@display-p3', (new HsvSpace(ColorSpaces::displayP3()))->id());
        self::assertSame('custom-hsl', (new HslSpace(ColorSpaces::displayP3(), 'custom-hsl'))->id());
        self::assertSame('HSL (Display P3)', (new HslSpace(ColorSpaces::displayP3()))->name());
        self::assertSame(ColorSpaces::displayP3(), (new HslSpace(ColorSpaces::displayP3()))->gamutSpace());
    }

    public function testCustomIdsForHwbAndHsv(): void
    {
        self::assertSame('custom-hwb', (new HwbSpace(ColorSpaces::srgb(), 'custom-hwb'))->id());
        self::assertSame('custom-hsv', (new HsvSpace(ColorSpaces::srgb(), 'custom-hsv'))->id());
    }

    public function testHslOfOutOfGamutLightnessZeroOrOne(): void
    {
        // L = 0 or 1 with max ≠ min (out of gamut): CSS sets the saturation to 0.
        self::assertSame([null, 0.0, 100.0], ColorSpaces::hsl()->fromBase([1.2, 1.0, 0.8]));
        self::assertSame([null, 0.0, 0.0], ColorSpaces::hsl()->fromBase([-0.2, 0.0, 0.2]));
    }

    public function testHsvSaturationIsZeroWhenValueIsZero(): void
    {
        $hsv = ColorSpaces::hsv()->fromBase([0.0, -0.2, -0.1]);

        self::assertSame(0.0, $hsv[1]);
        self::assertSame(0.0, $hsv[2]);
        self::assertNotNull($hsv[0], 'The chroma is 0.2, so the hue is defined.');
    }

    public function testHwbAtExactlyTheThreshold(): void
    {
        $hwb = ColorSpaces::hwb()->fromBase([0.99999, 0.99999, 1.0]);

        self::assertNull($hwb[0]);
    }

}
