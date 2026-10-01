<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Space;

use Domm98CZ\Color\Colorimetry\WhitePoints;
use Domm98CZ\Color\Space\ColorSpaceRegistry;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Definition\XyzSpace;
use Domm98CZ\Color\Space\Exception\DuplicateColorSpaceException;
use Domm98CZ\Color\Space\Exception\InvalidColorSpaceDefinitionException;
use Domm98CZ\Color\Space\Exception\UnknownColorSpaceException;
use Domm98CZ\Color\Tests\Fake\PercentSrgbSpace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ColorSpacesAndRegistryTest extends TestCase
{
    public function testBuiltInIds(): void
    {
        self::assertSame(
            [
                'xyz-d65', 'xyz-d50',
                'srgb', 'srgb-linear',
                'display-p3', 'display-p3-linear',
                'a98-rgb', 'a98-rgb-linear',
                'prophoto-rgb', 'prophoto-rgb-linear',
                'rec2020', 'rec2020-linear',
                'hsl', 'hwb', 'hsv',
                'lab', 'lch', 'lab-d65', 'lch-d65',
                'oklab', 'oklch',
            ],
            \array_map(static fn(ColorSpace $space): string => $space->id(), ColorSpaces::all()),
        );
    }

    public function testFactoriesReturnSharedInstances(): void
    {
        self::assertSame(ColorSpaces::oklch(), ColorSpaces::oklch());
        self::assertSame(ColorSpaces::srgb()->base(), ColorSpaces::srgbLinear());
        self::assertSame(ColorSpaces::oklch()->base(), ColorSpaces::oklab());
    }

    /**
     * @return iterable<string, array{ColorSpace, string|null, string}>
     */
    public static function structure(): iterable
    {
        yield 'srgb' => [ColorSpaces::srgb(), 'srgb', 'D65'];
        yield 'srgb-linear' => [ColorSpaces::srgbLinear(), 'srgb-linear', 'D65'];
        yield 'hsl' => [ColorSpaces::hsl(), 'srgb', 'D65'];
        yield 'hwb' => [ColorSpaces::hwb(), 'srgb', 'D65'];
        yield 'hsv' => [ColorSpaces::hsv(), 'srgb', 'D65'];
        yield 'display-p3' => [ColorSpaces::displayP3(), 'display-p3', 'D65'];
        yield 'prophoto-rgb' => [ColorSpaces::prophotoRgb(), 'prophoto-rgb', 'D50'];
        yield 'lab' => [ColorSpaces::lab(), null, 'D50'];
        yield 'lch' => [ColorSpaces::lch(), null, 'D50'];
        yield 'lab-d65' => [ColorSpaces::labD65(), null, 'D65'];
        yield 'oklab' => [ColorSpaces::oklab(), null, 'D65'];
        yield 'oklch' => [ColorSpaces::oklch(), null, 'D65'];
        yield 'xyz-d50' => [ColorSpaces::xyzD50(), null, 'D50'];
    }

    #[DataProvider('structure')]
    public function testGamutSpaceAndWhitePoint(ColorSpace $space, ?string $gamutSpaceId, string $white): void
    {
        self::assertSame($gamutSpaceId, $space->gamutSpace()?->id());
        self::assertSame($white, $space->whitePoint()->name);
        self::assertCount(3, $space->channels());
        self::assertNotSame('', $space->name());
    }

    public function testRootsHaveNoBase(): void
    {
        self::assertNull(ColorSpaces::xyzD65()->base());
        self::assertSame([0.1, 0.2, 0.3], ColorSpaces::xyzD65()->toBase([0.1, 0.2, 0.3]));
        self::assertSame([0.1, 0.2, 0.3], ColorSpaces::xyzD65()->fromBase([0.1, 0.2, 0.3]));
    }

    public function testDefaultRegistryContainsAllBuiltIns(): void
    {
        $registry = ColorSpaceRegistry::default();

        self::assertCount(\count(ColorSpaces::all()), $registry->all());
        self::assertTrue($registry->has('oklch'));
        self::assertFalse($registry->has('cmyk'));
        self::assertSame(ColorSpaces::lab(), $registry->get('lab'));
    }

    public function testRegistryRejectsUnknownAndDuplicateIds(): void
    {
        try {
            ColorSpaceRegistry::default()->get('xyz');
            self::fail('Unknown id must throw.');
        } catch (UnknownColorSpaceException $exception) {
            self::assertStringContainsString('"xyz"', $exception->getMessage());
        }

        $this->expectException(DuplicateColorSpaceException::class);
        ColorSpaceRegistry::default()->with(new XyzSpace('srgb', 'Clash', WhitePoints::d65()));
    }

    public function testRegistryIsImmutableAndExtensible(): void
    {
        $default = ColorSpaceRegistry::default();
        $extended = $default->with(new PercentSrgbSpace());

        self::assertFalse($default->has('test-percent-srgb'));
        self::assertTrue($extended->has('test-percent-srgb'));
        self::assertSame([], ColorSpaceRegistry::empty()->all());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIds(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['XYZ'];
        yield 'space' => ['my space'];
        yield 'trailing dash' => ['xyz-'];
    }

    #[DataProvider('invalidIds')]
    public function testSpaceIdsAreValidated(string $id): void
    {
        $this->expectException(InvalidColorSpaceDefinitionException::class);

        new XyzSpace($id, 'Name', WhitePoints::d65());
    }

    public function testSpaceNamesAreValidated(): void
    {
        $this->expectException(InvalidColorSpaceDefinitionException::class);

        new XyzSpace('xyz-test', '  ', WhitePoints::d65());
    }

    public function testEveryFactoryMatchesTheRegistry(): void
    {
        $registry = ColorSpaceRegistry::default();

        foreach ([
            'xyz-d65' => ColorSpaces::xyzD65(), 'xyz-d50' => ColorSpaces::xyzD50(),
            'srgb' => ColorSpaces::srgb(), 'srgb-linear' => ColorSpaces::srgbLinear(),
            'display-p3' => ColorSpaces::displayP3(), 'display-p3-linear' => ColorSpaces::displayP3Linear(),
            'a98-rgb' => ColorSpaces::a98Rgb(), 'a98-rgb-linear' => ColorSpaces::a98RgbLinear(),
            'prophoto-rgb' => ColorSpaces::prophotoRgb(), 'prophoto-rgb-linear' => ColorSpaces::prophotoRgbLinear(),
            'rec2020' => ColorSpaces::rec2020(), 'rec2020-linear' => ColorSpaces::rec2020Linear(),
            'hsl' => ColorSpaces::hsl(), 'hwb' => ColorSpaces::hwb(), 'hsv' => ColorSpaces::hsv(),
            'lab' => ColorSpaces::lab(), 'lch' => ColorSpaces::lch(), 'lab-d65' => ColorSpaces::labD65(), 'lch-d65' => ColorSpaces::lchD65(),
            'oklab' => ColorSpaces::oklab(), 'oklch' => ColorSpaces::oklch(),
        ] as $id => $space) {
            self::assertSame($id, $space->id());
            self::assertSame($space, $registry->get($id));
        }
    }

    public function testRegistryListIsAList(): void
    {
        self::assertTrue(\array_is_list(ColorSpaceRegistry::default()->all()));
    }

    public function testNamesOfDerivedSpaces(): void
    {
        self::assertSame('HSL (sRGB)', ColorSpaces::hsl()->name());
        self::assertSame('HWB (sRGB)', ColorSpaces::hwb()->name());
        self::assertSame('HSV (sRGB)', ColorSpaces::hsv()->name());
        self::assertSame('sRGB (linear)', ColorSpaces::srgbLinear()->name());
        self::assertSame('CIE XYZ (D50)', ColorSpaces::xyzD50()->name());
        self::assertSame('OKLCh', ColorSpaces::oklch()->name());
    }

}
