<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Notation;

use Domm98CZ\Color\Colorimetry\WhitePoints;
use Domm98CZ\Color\Notation\CssFormat;
use Domm98CZ\Color\Notation\CssParser;
use Domm98CZ\Color\Notation\CssSerializer;
use Domm98CZ\Color\Notation\Exception\InvalidCssFormatException;
use Domm98CZ\Color\Notation\Internal\DecimalFormatter;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\ColorValue;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Definition\HslSpace;
use Domm98CZ\Color\Space\Definition\XyzSpace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CssSerializerTest extends TestCase
{
    /**
     * @return iterable<string, array{ColorSpace, list<float|null>, float|null, string}>
     */
    public static function forms(): iterable
    {
        $srgb = ColorSpaces::srgb();

        yield 'rgb' => [$srgb, [0.2, 0.4, 1.0], 1.0, 'rgb(51, 102, 255)'];
        yield 'rgba' => [$srgb, [0.2, 0.4, 1.0], 0.5, 'rgba(51, 102, 255, 0.5)'];
        yield 'rgba alpha 0' => [$srgb, [0.0, 0.0, 0.0], 0.0, 'rgba(0, 0, 0, 0)'];
        yield 'fractional channels' => [$srgb, [0.5, 0.25, 0.75], 1.0, 'rgb(127.5, 63.75, 191.25)'];
        yield 'exactly at the lower tolerance' => [$srgb, [-CssSerializer::SRGB_TOLERANCE, 0.0, 0.0], 1.0, 'rgb(0, 0, 0)'];
        yield 'exactly at the upper tolerance' => [$srgb, [1.0 + CssSerializer::SRGB_TOLERANCE, 0.0, 0.0], 1.0, 'rgb(255, 0, 0)'];
        yield 'within tolerance above 1' => [$srgb, [1.0000009, 0.0, -0.0000009], 1.0, 'rgb(255, 0, 0)'];
        yield 'above 1 beyond tolerance' => [$srgb, [1.00001, 0.0, 0.0], 1.0, 'color(srgb 1.00001 0 0)'];
        yield 'below 0 beyond tolerance' => [$srgb, [0.0, -0.00001, 0.0], 1.0, 'color(srgb 0 -0.00001 0)'];
        yield 'srgb missing component' => [$srgb, [null, 0.0, 0.0], 1.0, 'color(srgb none 0 0)'];
        yield 'srgb missing alpha' => [$srgb, [1.0, 0.0, 0.0], null, 'color(srgb 1 0 0 / none)'];
        yield 'srgb missing with alpha' => [$srgb, [null, 0.5, 0.0], 0.25, 'color(srgb none 0.5 0 / 0.25)'];

        yield 'hsl as rgb' => [ColorSpaces::hsl(), [120.0, 100.0, 50.0], 1.0, 'rgb(0, 255, 0)'];
        yield 'hsl out of sRGB as color(srgb)' => [ColorSpaces::hsl(), [0.0, 100.0, 150.0], 1.0, 'color(srgb 1 2 2)'];
        yield 'hsl with none' => [ColorSpaces::hsl(), [null, 50.0, 25.5], 0.5, 'hsl(none 50% 25.5% / 0.5)'];
        yield 'hsl with none alpha' => [ColorSpaces::hsl(), [120.0, 50.0, 25.0], null, 'hsl(120 50% 25% / none)'];
        yield 'hsl negative saturation goes through sRGB' => [ColorSpaces::hsl(), [null, -10.0, 50.0], 1.0, 'rgb(114.75, 140.25, 140.25)'];
        yield 'hsl negative saturation with missing lightness' => [ColorSpaces::hsl(), [120.0, -10.0, null], 1.0, 'rgb(0, 0, 0)'];
        yield 'hsl zero saturation keeps the modern form' => [ColorSpaces::hsl(), [null, 0.0, 50.0], 1.0, 'hsl(none 0% 50%)'];
        yield 'hwb negative whiteness keeps the modern form' => [ColorSpaces::hwb(), [20.0, -10.0, null], 1.0, 'hwb(20 -10% none)'];
        yield 'hwb as rgb' => [ColorSpaces::hwb(), [150.0, 20.0, 10.0], 1.0, 'rgb(51, 229.5, 140.25)'];
        yield 'hwb with none' => [ColorSpaces::hwb(), [20.0, null, 30.0], null, 'hwb(20 none 30% / none)'];

        yield 'lab' => [ColorSpaces::lab(), [56.2, 0.0, 83.6], 1.0, 'lab(56.2 0 83.6)'];
        yield 'lab alpha and none' => [ColorSpaces::lab(), [null, -10.5, 20.0], 0.93, 'lab(none -10.5 20 / 0.93)'];
        yield 'lab L 100 kept' => [ColorSpaces::lab(), [100.0, 0.0, 0.0], 1.0, 'lab(100 0 0)'];
        yield 'lab L above 100 as xyz-d50' => [ColorSpaces::lab(), [100.5, 0.0, 0.0], 1.0, 'color(xyz-d50 0.976819 1.012987 0.83582)'];
        yield 'lab L below 0 as xyz-d50' => [ColorSpaces::lab(), [-1.0, 0.0, 0.0], 1.0, 'color(xyz-d50 -0.001068 -0.001107 -0.000913)'];
        yield 'lch' => [ColorSpaces::lch(), [37.0, 105.0, 305.0], 1.0, 'lch(37 105 305)'];
        yield 'lch none hue' => [ColorSpaces::lch(), [37.0, 0.0, null], 1.0, 'lch(37 0 none)'];
        yield 'lch negative chroma as xyz-d50' => [ColorSpaces::lch(), [50.0, -10.0, 0.0], 1.0, 'color(xyz-d50 0.159531 0.184187 0.151973)'];
        yield 'lch negative chroma, missing L' => [ColorSpaces::lch(), [null, -10.0, 0.0], 1.0, 'color(xyz-d50 -0.002477 0 0)'];
        yield 'lch negative chroma, missing hue' => [ColorSpaces::lch(), [50.0, -10.0, null], 1.0, 'color(xyz-d50 0.159531 0.184187 0.151973)'];
        yield 'oklab' => [ColorSpaces::oklab(), [0.54, -0.1, -0.02], 1.0, 'oklab(0.54 -0.1 -0.02)'];
        yield 'oklab L above 1 as xyz-d65' => [ColorSpaces::oklab(), [1.5, 0.0, 0.0], 1.0, 'color(xyz-d65 3.207789 3.375 3.67557)'];
        yield 'oklab L 1 kept' => [ColorSpaces::oklab(), [1.0, 0.0, 0.0], 1.0, 'oklab(1 0 0)'];
        yield 'oklab L 0 kept' => [ColorSpaces::oklab(), [0.0, 0.0, 0.0], 1.0, 'oklab(0 0 0)'];
        yield 'oklch' => [ColorSpaces::oklch(), [0.5385, 0.1725, 320.67], 0.7, 'oklch(0.5385 0.1725 320.67 / 0.7)'];
        yield 'oklch zero chroma kept' => [ColorSpaces::oklch(), [0.5, 0.0, 0.0], 1.0, 'oklch(0.5 0 0)'];
        yield 'oklch negative chroma as xyz-d65' => [ColorSpaces::oklch(), [0.5, -0.01, 0.0], 1.0, 'color(xyz-d65 0.114936 0.125954 0.13709)'];

        yield 'srgb-linear' => [ColorSpaces::srgbLinear(), [0.5, 0.0, 1.0], 1.0, 'color(srgb-linear 0.5 0 1)'];
        yield 'display-p3' => [ColorSpaces::displayP3(), [1.0, 0.0, 0.0], 0.5, 'color(display-p3 1 0 0 / 0.5)'];
        yield 'display-p3-linear' => [ColorSpaces::displayP3Linear(), [0.0, 0.0, 0.0], 1.0, 'color(display-p3-linear 0 0 0)'];
        yield 'a98-rgb' => [ColorSpaces::a98Rgb(), [0.0, 0.0, 0.0], 1.0, 'color(a98-rgb 0 0 0)'];
        yield 'prophoto-rgb' => [ColorSpaces::prophotoRgb(), [0.0, 0.0, 0.0], 1.0, 'color(prophoto-rgb 0 0 0)'];
        yield 'rec2020' => [ColorSpaces::rec2020(), [0.0, 0.0, null], 1.0, 'color(rec2020 0 0 none)'];
        yield 'xyz-d65' => [ColorSpaces::xyzD65(), [0.472, 0.372, 0.131], 1.0, 'color(xyz-d65 0.472 0.372 0.131)'];
        yield 'xyz-d50' => [ColorSpaces::xyzD50(), [0.472, 0.372, 0.131], 1.0, 'color(xyz-d50 0.472 0.372 0.131)'];

        yield 'hsv via its sRGB base' => [ColorSpaces::hsv(), [200.0, 50.0, 50.0], 1.0, 'rgb(63.75, 106.25, 127.5)'];
        yield 'lab-d65 via xyz-d65' => [ColorSpaces::labD65(), [100.0, 0.0, 0.0], 1.0, 'color(xyz-d65 0.950456 1 1.089058)'];
        yield 'lch-d65 via xyz-d65' => [ColorSpaces::lchD65(), [100.0, 0.0, 0.0], 1.0, 'color(xyz-d65 0.950456 1 1.089058)'];
        yield 'rec2020-linear via xyz-d65' => [ColorSpaces::rec2020Linear(), [0.0, 0.0, 0.0], 1.0, 'color(xyz-d65 0 0 0)'];
        yield 'hsl over display-p3 via display-p3' => [new HslSpace(ColorSpaces::displayP3()), [0.0, 100.0, 50.0], 1.0, 'color(display-p3 1 0 0)'];
        yield 'unknown root via xyz-d65' => [new XyzSpace('xyz-test', 'Test XYZ', WhitePoints::d65()), [0.5, 0.5, 0.5], 1.0, 'color(xyz-d65 0.5 0.5 0.5)'];
    }

    /**
     * @param list<float|null> $coordinates
     */
    #[DataProvider('forms')]
    public function testForms(ColorSpace $space, array $coordinates, ?float $alpha, string $css): void
    {
        self::assertSame($css, (new CssSerializer())->serialize(new ColorValue($space, $coordinates, $alpha)));
    }

    /**
     * @param list<float|null> $coordinates
     */
    #[DataProvider('forms')]
    public function testOutputParsesBackToTheSameColor(ColorSpace $space, array $coordinates, ?float $alpha, string $css): void
    {
        $parsed = (new CssParser())->parse($css);

        self::assertSame($css, (new CssSerializer())->serialize($parsed), 'serialisation is idempotent');
        self::assertSame($alpha, $parsed->alpha === null || $alpha === null ? $parsed->alpha : \round($parsed->alpha, 6));
    }

    public function testLegacyFormBoundary(): void
    {
        $serializer = new CssSerializer();
        $justOutside = $serializer->serialize(new ColorValue(ColorSpaces::srgb(), [1.0000011, 0.0, 0.0]));

        // 1.0000011 is outside the tolerance; rounded to 6 digits it is 1.000001, which (as parsed)
        // lies within 1e-6 of the range, so the second serialisation uses rgb() and clamps by ≤ 1e-6.
        self::assertSame('color(srgb 1.000001 0 0)', $justOutside);
        self::assertSame('rgb(255, 0, 0)', $serializer->serialize((new CssParser())->parse($justOutside)));
    }

    public function testPrecisionIsConfigurable(): void
    {
        $value = new ColorValue(ColorSpaces::displayP3(), [0.964, 0.763, 0.787], 1 / 3);

        self::assertSame('color(display-p3 0.96 0.76 0.79 / 0.33)', (new CssSerializer(new CssFormat(2)))->serialize($value));
        self::assertSame('color(display-p3 1 1 1)', (new CssSerializer(new CssFormat(0)))->serialize(new ColorValue(ColorSpaces::displayP3(), [0.964, 0.763, 0.5])));
        self::assertSame('color(display-p3 0.964 0.763 0.787 / 0.333333333333333)', (new CssSerializer(new CssFormat(15)))->serialize($value));
        self::assertSame(6, (new CssFormat())->fractionDigits);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidFractionDigits(): iterable
    {
        yield 'negative' => [-1];
        yield 'too many' => [16];
    }

    #[DataProvider('invalidFractionDigits')]
    public function testFractionDigitsAreValidated(int $digits): void
    {
        $this->expectException(InvalidCssFormatException::class);
        $this->expectExceptionMessage('between 0 and 15');

        new CssFormat($digits);
    }

    /**
     * @return iterable<string, array{float, int, string}>
     */
    public static function numbers(): iterable
    {
        yield 'half up' => [0.125, 2, '0.13'];
        yield 'half towards +infinity for negatives' => [-0.125, 2, '-0.12'];
        yield 'rounds up, not truncated' => [0.787, 2, '0.79'];
        yield 'binary value below the half' => [1.005, 2, '1'];
        yield 'trailing zeros removed' => [0.5, 6, '0.5'];
        yield 'integer' => [255.0, 6, '255'];
        yield 'leading zero kept' => [0.05, 6, '0.05'];
        yield 'tiny positive is zero' => [1e-7, 6, '0'];
        yield 'tiny negative is zero, not -0' => [-1e-7, 6, '0'];
        yield 'negative zero' => [-0.0, 3, '0'];
        yield 'small negative kept' => [-6e-7, 6, '-0.000001'];
        yield 'no fraction digits' => [2.5, 0, '3'];
        yield 'no fraction digits negative half' => [-0.5, 0, '0'];
        yield 'negative integer' => [-160.0, 6, '-160'];
        yield 'fraction padding' => [0.000123, 6, '0.000123'];
        yield 'large value, no exponent' => [1.0e20, 6, '100000000000000000000'];
        yield 'large negative value' => [-1.0e20, 2, '-100000000000000000000'];
        yield 'beyond exact integers, fewer digits than stored' => [1000000000000000.25, 1, '1000000000000000.2'];
        yield 'at the exact-integer limit' => [9007199254.740992, 6, '9007199254.740992'];
    }

    #[DataProvider('numbers')]
    public function testNumberFormatting(float $value, int $digits, string $expected): void
    {
        self::assertSame($expected, DecimalFormatter::format($value, $digits));
    }
}
