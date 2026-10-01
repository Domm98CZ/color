<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Gamut;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Gamut\Algorithm\Clip;
use Domm98CZ\Color\Gamut\Algorithm\CssColor4RayTrace;
use Domm98CZ\Color\Gamut\Contract\GamutMapper;
use Domm98CZ\Color\Gamut\Exception\UnsupportedGamutException;
use Domm98CZ\Color\Gamut\GamutMappers;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Fake\PercentSrgbSpace;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GamutMappersTest extends TestCase
{
    use FloatAssertions;

    /**
     * @return iterable<string, array{GamutMapper, string}>
     */
    public static function mappers(): iterable
    {
        yield 'css color 4' => [GamutMappers::cssColor4(), 'css-color-4-binary-search-local-minde@r1'];
        yield 'ray trace' => [GamutMappers::rayTrace(), 'css-color-4-ray-trace@r1'];
        yield 'chroma reduction' => [GamutMappers::chromaReduction(), 'oklch-chroma-reduction@r1'];
        yield 'clip' => [GamutMappers::clip(), 'clip@r1'];
    }

    #[DataProvider('mappers')]
    public function testInGamutColorsAreOnlyConverted(GamutMapper $mapper, string $identifier): void
    {
        $color = Color::fromRgb(51, 102, 204);
        $result = $mapper->map($color, ColorSpaces::displayP3());

        self::assertSame($identifier, $mapper->id()->identifier());
        self::assertSame($identifier, $result->method->identifier());
        self::assertTrue($result->wasInGamut);
        self::assertSame($color, $result->original);
        self::assertSame('display-p3', $result->destination->id());
        self::assertTrue($result->color->isCloseTo($color->to(ColorSpaces::displayP3()), Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB)));
        self::assertLessThan(Tolerances::CONVERSION_ROUND_TRIP_SRGB, $result->difference->value);
        self::assertSame('deltae-ok@r1', $result->difference->metric->identifier());
        self::assertStringContainsString('§ 14', (string) $mapper->id()->sources[0]->section);
    }

    #[DataProvider('mappers')]
    public function testOutOfGamutColorsAreMapped(GamutMapper $mapper, string $identifier): void
    {
        $p3Green = Color::of(ColorSpaces::displayP3(), [0.0, 1.0, 0.0], 0.5);
        $result = $mapper->map($p3Green, ColorSpaces::srgb());

        self::assertFalse($result->wasInGamut, $identifier);
        self::assertSame('srgb', $result->color->space()->id());
        self::assertTrue($result->color->isInGamut(null, Tolerance::exact()), 'strictly in gamut');
        self::assertSame(0.5, $result->color->alpha(), 'alpha is kept');
        self::assertGreaterThan(0.0, $result->difference->value);
    }

    #[DataProvider('mappers')]
    public function testMissingAlphaIsKept(GamutMapper $mapper, string $identifier): void
    {
        self::assertTrue($mapper->map(Color::of(ColorSpaces::displayP3(), [1.0, 0.0, 0.0], null), ColorSpaces::srgb())->color->isAlphaMissing(), $identifier);
        self::assertTrue($mapper->map(Color::fromOklch(1.5, 0.1, 30)->withAlpha(null), ColorSpaces::srgb())->color->isAlphaMissing(), $identifier);
    }

    #[DataProvider('mappers')]
    public function testUnboundedDestinationOnlyConverts(GamutMapper $mapper, string $identifier): void
    {
        $color = Color::of(ColorSpaces::displayP3(), [1.2, -0.1, 0.0]);
        $result = $mapper->map($color, ColorSpaces::oklab());

        self::assertTrue($result->color->equals($color->to(ColorSpaces::oklab())), $identifier);
        self::assertTrue($result->wasInGamut);
    }

    /**
     * @return iterable<string, array{GamutMapper}>
     */
    public static function perceptualMappers(): iterable
    {
        yield 'css color 4' => [GamutMappers::cssColor4()];
        yield 'ray trace' => [GamutMappers::rayTrace()];
        yield 'chroma reduction' => [GamutMappers::chromaReduction()];
    }

    #[DataProvider('perceptualMappers')]
    public function testLightnessOutOfRangeGivesWhiteOrBlack(GamutMapper $mapper): void
    {
        self::assertSame([1.0, 1.0, 1.0], \array_map(static fn(?float $v): float => \round($v ?? 0.0, 9), $mapper->map(Color::fromOklch(1.0, 0.2, 30, 0.4), ColorSpaces::srgb())->color->coordinates()));
        self::assertSame(0.4, $mapper->map(Color::fromOklch(1.2, 0.2, 30, 0.4), ColorSpaces::srgb())->color->alpha());
        self::assertSame([0.0, 0.0, 0.0], \array_map(static fn(?float $v): float => \round($v ?? 0.0, 9) + 0.0, $mapper->map(Color::fromOklch(0.0, 0.2, 30), ColorSpaces::srgb())->color->coordinates()));
        self::assertSame([0.0, 0.0, 0.0], \array_map(static fn(?float $v): float => \round($v ?? 0.0, 9) + 0.0, $mapper->map(Color::fromOklch(-0.1, 0.2, 30), ColorSpaces::srgb())->color->coordinates()));
    }

    #[DataProvider('perceptualMappers')]
    public function testCylindricalDestinationUsesItsRgbGamut(GamutMapper $mapper): void
    {
        $result = $mapper->map(Color::of(ColorSpaces::displayP3(), [1.0, 0.0, 0.0]), ColorSpaces::hsl());

        self::assertSame('hsl', $result->color->space()->id());
        self::assertTrue($result->color->isInGamut());
        self::assertTrue($result->color->isInGamut(ColorSpaces::srgb()));
    }

    #[DataProvider('perceptualMappers')]
    public function testHueAndLightnessAreKept(GamutMapper $mapper): void
    {
        $origin = Color::fromOklch(0.6, 0.35, 150.0);
        $mapped = $mapper->map($origin, ColorSpaces::srgb())->color->to(ColorSpaces::oklch())->coordinates();

        $chroma = $mapped[1] ?? 1.0;

        self::assertFloatClose(0.6, $mapped[0] ?? 0.0, Tolerance::absolute(Tolerances::OKLCH_JND));
        self::assertFloatClose(150.0, $mapped[2] ?? 0.0, Tolerance::absolute(\rad2deg(Tolerances::OKLCH_JND / $chroma)));
        self::assertLessThan(0.35, $chroma);
    }

    public function testClipClampsTheGammaEncodedValues(): void
    {
        $result = GamutMappers::clip()->map(Color::of(ColorSpaces::srgbLinear(), [0.5, 1.0, 3.0]), ColorSpaces::srgbLinear());

        self::assertSame([0.5, 1.0, 1.0], $result->color->coordinates());
        self::assertSame([0.0, 1.0, null], GamutMappers::clip()->map(Color::of(ColorSpaces::srgb(), [-0.5, 2.0, null]), ColorSpaces::srgb())->color->coordinates(), 'missing stays missing');
    }

    public function testBinarySearchReturnsTheClippedColorWhenItIsWithinOneJnd(): void
    {
        // Just outside sRGB: clipping changes it by far less than the JND 0.02.
        $slightly = Color::fromSrgb(1.001, 0.5, 0.2);
        $result = GamutMappers::cssColor4()->map($slightly, ColorSpaces::srgb());

        self::assertSame([1.0, 0.5, 0.2], \array_map(static fn(?float $v): float => \round($v ?? 0.0, 12), $result->color->coordinates()));
    }

    public function testFactories(): void
    {
        self::assertEquals(new Clip(), GamutMappers::clip());
        self::assertEquals(new CssColor4RayTrace(), GamutMappers::rayTrace());
    }

    public function testRayTraceNeedsAnRgbGamut(): void
    {
        $this->expectException(UnsupportedGamutException::class);
        $this->expectExceptionMessage('"test-percent-srgb" is not one');

        GamutMappers::rayTrace()->map(Color::fromRgb(0, 0, 0), new PercentSrgbSpace(ownGamut: true));
    }

    public function testRayTraceWithALinearDestination(): void
    {
        $result = GamutMappers::rayTrace()->map(Color::of(ColorSpaces::displayP3(), [0.0, 1.0, 0.0]), ColorSpaces::srgbLinear());

        self::assertSame('srgb-linear', $result->color->space()->id());
        self::assertTrue($result->color->isInGamut());
    }

    public function testOtherMappersWorkWithAnyBoundedSpace(): void
    {
        $percent = new PercentSrgbSpace(ownGamut: true);
        $result = GamutMappers::cssColor4()->map(Color::of(ColorSpaces::displayP3(), [0.0, 1.0, 0.0]), $percent);

        self::assertSame('test-percent-srgb', $result->color->space()->id());
        self::assertTrue($result->color->isInGamut());
        self::assertSame([0.0, 100.0, 50.0], GamutMappers::clip()->map(Color::of($percent, [-5.0, 120.0, 50.0]), $percent)->color->coordinates());
    }
}
