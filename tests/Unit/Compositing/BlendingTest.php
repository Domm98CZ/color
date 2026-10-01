<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Compositing;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Compositing\Algorithm\Composite;
use Domm98CZ\Color\Compositing\Algorithm\SourceOver;
use Domm98CZ\Color\Compositing\BlendMode;
use Domm98CZ\Color\Compositing\Compositor;
use Domm98CZ\Color\Compositing\Exception\InvalidCompositingSpaceException;
use Domm98CZ\Color\Compositing\PorterDuff;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Blend modes, Porter–Duff operators and the general formula (W3C Compositing and Blending
 * Level 1, § 6, § 9.1, § 10). Expected values are derived from the definitions by hand, one case
 * per branch.
 */
final class BlendingTest extends TestCase
{
    use FloatAssertions;

    /**
     * @return iterable<string, array{BlendMode, float, float, float}>
     */
    public static function separable(): iterable
    {
        yield 'normal' => [BlendMode::Normal, 0.6, 0.3, 0.3];
        yield 'multiply' => [BlendMode::Multiply, 0.6, 0.3, 0.18];
        yield 'screen' => [BlendMode::Screen, 0.6, 0.3, 0.72];
        yield 'overlay, backdrop above 0.5 screens' => [BlendMode::Overlay, 0.6, 0.3, 0.44];
        yield 'overlay, backdrop below 0.5 multiplies' => [BlendMode::Overlay, 0.2, 0.3, 0.12];
        yield 'darken' => [BlendMode::Darken, 0.6, 0.3, 0.3];
        yield 'lighten' => [BlendMode::Lighten, 0.6, 0.3, 0.6];
        yield 'color-dodge' => [BlendMode::ColorDodge, 0.6, 0.3, 0.6 / 0.7];
        yield 'color-dodge, black backdrop wins over a white source' => [BlendMode::ColorDodge, 0.0, 1.0, 0.0];
        yield 'color-dodge, white source' => [BlendMode::ColorDodge, 0.1, 1.0, 1.0];
        yield 'color-dodge, white backdrop' => [BlendMode::ColorDodge, 1.0, 0.3, 1.0];
        yield 'color-dodge, capped' => [BlendMode::ColorDodge, 0.6, 0.5, 1.0];
        yield 'color-burn' => [BlendMode::ColorBurn, 0.6, 0.8, 0.5];
        yield 'color-burn, capped' => [BlendMode::ColorBurn, 0.6, 0.3, 0.0];
        yield 'color-burn, white backdrop wins over a black source' => [BlendMode::ColorBurn, 1.0, 0.0, 1.0];
        yield 'color-burn, black backdrop' => [BlendMode::ColorBurn, 0.0, 0.5, 0.0];
        yield 'color-burn, black source' => [BlendMode::ColorBurn, 0.9, 0.0, 0.0];
        yield 'hard-light, dark source multiplies' => [BlendMode::HardLight, 0.6, 0.3, 0.36];
        yield 'hard-light, light source screens' => [BlendMode::HardLight, 0.6, 0.8, 0.84];
        yield 'soft-light, dark source' => [BlendMode::SoftLight, 0.6, 0.3, 0.504];
        yield 'soft-light, light source, light backdrop' => [BlendMode::SoftLight, 0.6, 0.8, 0.6 + 0.6 * (\sqrt(0.6) - 0.6)];
        yield 'soft-light, light source, dark backdrop' => [BlendMode::SoftLight, 0.2, 0.8, 0.3488];
        yield 'difference' => [BlendMode::Difference, 0.3, 0.6, 0.3];
        yield 'exclusion' => [BlendMode::Exclusion, 0.6, 0.3, 0.54];
        yield 'inputs are clamped to the color range' => [BlendMode::Multiply, 1.5, 0.3, 0.3];
        yield 'negative inputs are clamped' => [BlendMode::Screen, -0.5, 0.3, 0.3];
        yield 'the result is in the color range' => [BlendMode::Normal, 0.5, 1.2, 1.0];
    }

    #[DataProvider('separable')]
    public function testSeparableModes(BlendMode $mode, float $backdrop, float $source, float $expected): void
    {
        $result = $mode->blend([$backdrop, $backdrop, $backdrop], [$source, $source, $source]);

        self::assertTrue($mode->isSeparable());
        foreach ($result as $value) {
            self::assertFloatClose($expected, $value, Tolerances::linearAlgebra());
        }
    }

    /**
     * @return iterable<string, array{BlendMode, array{float, float, float}, array{float, float, float}, array{float, float, float}}>
     */
    public static function nonSeparable(): iterable
    {
        $backdrop = [0.2, 0.4, 0.6];
        $source = [0.9, 0.1, 0.5];

        yield 'hue' => [BlendMode::Hue, $backdrop, $source, [0.62, 0.22, 0.42]];
        yield 'saturation' => [BlendMode::Saturation, $backdrop, $source, [0.038, 0.438, 0.838]];
        yield 'color' => [BlendMode::Color, $backdrop, $source, [0.878, 0.078, 0.478]];
        yield 'luminosity' => [BlendMode::Luminosity, $backdrop, $source, [0.222, 0.422, 0.622]];
        yield 'ClipColor above 1' => [BlendMode::Color, [0.5, 0.5, 0.5], [1.0, 0.0, 0.0], [1.0, 0.5 - 0.3 * 0.5 / 0.7, 0.5 - 0.3 * 0.5 / 0.7]];
        yield 'ClipColor below 0' => [BlendMode::Luminosity, [1.0, 0.0, 0.0], [0.0, 0.0, 10 / 11], [1 / 3, 0.0, 0.0]];
        yield 'ClipColor below 0, distinct channels' => [BlendMode::Luminosity, [1.0, 0.0, 0.2], [0.0, 0.0, 10 / 11], [0.1 + 0.678 * 0.1 / 0.322, 0.0, 0.1 - 0.122 * 0.1 / 0.322]];
        yield 'ClipColor below 0, minimum in the first channel' => [BlendMode::Luminosity, [0.0, 1.0, 0.2], [0.0, 0.0, 10 / 11], [0.0, 0.1 + 0.388 * 0.1 / 0.612, 0.1 - 0.412 * 0.1 / 0.612]];
        yield 'ClipColor above 1, distinct channels' => [BlendMode::Color, [0.9, 0.9, 0.9], [0.5, 1.0, 0.0], [0.9 - 0.24 * 0.1 / 0.26, 1.0, 0.9 - 0.74 * 0.1 / 0.26]];
        yield 'luminosity of black: minimum exactly 0' => [BlendMode::Luminosity, [0.5, 0.5, 0.5], [0.0, 0.0, 0.0], [0.0, 0.0, 0.0]];
        yield 'luminosity of white: maximum exactly 1' => [BlendMode::Luminosity, [0.5, 0.5, 0.5], [1.0, 1.0, 1.0], [1.0, 1.0, 1.0]];
        yield 'saturation of a gray backdrop changes nothing' => [BlendMode::Saturation, [0.5, 0.5, 0.5], $source, [0.5, 0.5, 0.5]];
        yield 'hue with equal source components' => [BlendMode::Hue, $backdrop, [0.9, 0.9, 0.1], [0.406, 0.406, 0.006]];
    }

    /**
     * @param array{float, float, float} $backdrop
     * @param array{float, float, float} $source
     * @param array{float, float, float} $expected
     */
    #[DataProvider('nonSeparable')]
    public function testNonSeparableModes(BlendMode $mode, array $backdrop, array $source, array $expected): void
    {
        $result = $mode->blend($backdrop, $source);

        self::assertFalse($mode->isSeparable());
        foreach ($expected as $index => $value) {
            self::assertFloatClose($value, $result[$index], Tolerances::linearAlgebra(), (string) $index);
        }
    }

    public function testThereAreSixteenModesWithCssKeywords(): void
    {
        self::assertSame(
            ['normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'color-burn', 'hard-light', 'soft-light', 'difference', 'exclusion', 'hue', 'saturation', 'color', 'luminosity'],
            \array_map(static fn(BlendMode $m): string => $m->value, BlendMode::cases()),
        );
    }

    /**
     * @return iterable<string, array{PorterDuff, float, float}>
     */
    public static function fractions(): iterable
    {
        // αs = 0.25, αb = 0.5 (§ 9.1.1–9.1.13)
        yield 'clear' => [PorterDuff::Clear, 0.0, 0.0];
        yield 'copy' => [PorterDuff::Copy, 1.0, 0.0];
        yield 'destination' => [PorterDuff::Destination, 0.0, 1.0];
        yield 'source-over' => [PorterDuff::SourceOver, 1.0, 0.75];
        yield 'destination-over' => [PorterDuff::DestinationOver, 0.5, 1.0];
        yield 'source-in' => [PorterDuff::SourceIn, 0.5, 0.0];
        yield 'destination-in' => [PorterDuff::DestinationIn, 0.0, 0.25];
        yield 'source-out' => [PorterDuff::SourceOut, 0.5, 0.0];
        yield 'destination-out' => [PorterDuff::DestinationOut, 0.0, 0.75];
        yield 'source-atop' => [PorterDuff::SourceAtop, 0.5, 0.75];
        yield 'destination-atop' => [PorterDuff::DestinationAtop, 0.5, 0.25];
        yield 'xor' => [PorterDuff::Xor, 0.5, 0.75];
        yield 'lighter' => [PorterDuff::Lighter, 1.0, 1.0];
    }

    #[DataProvider('fractions')]
    public function testPorterDuffFractions(PorterDuff $operator, float $fa, float $fb): void
    {
        self::assertSame($fa, $operator->sourceFraction(0.25, 0.5));
        self::assertSame($fb, $operator->backdropFraction(0.25, 0.5));
    }

    public function testGeneralFormulaWithBlendingAndTranslucentLayers(): void
    {
        // B = 0.18; Cs′ = 0.5·0.3 + 0.5·0.18 = 0.24; αo = 0.75; Co = (0.5·0.24 + 0.25·0.6) / 0.75 = 0.36
        $result = Compositor::blend(BlendMode::Multiply)->composite(Color::fromSrgb(0.3, 0.3, 0.3, 0.5), Color::fromSrgb(0.6, 0.6, 0.6, 0.5));

        self::assertSame(0.75, $result->alpha());
        foreach ($result->coordinates() as $value) {
            self::assertFloatClose(0.36, $value ?? \NAN, Tolerances::linearAlgebra());
        }
    }

    public function testBlendingUsesEachChannel(): void
    {
        // multiply per channel: (0.2·0.5, 0.4·0.25, 0.6·1) over an opaque backdrop
        $result = Compositor::blend(BlendMode::Multiply)->composite(Color::fromSrgb(0.5, 0.25, 1.0), Color::fromSrgb(0.2, 0.4, 0.6));

        foreach ([0.1, 0.1, 0.6] as $index => $value) {
            self::assertFloatClose($value, $result->coordinates()[$index] ?? \NAN, Tolerances::linearAlgebra(), (string) $index);
        }
    }

    public function testMissingValuesCountAsZeroWhenBlending(): void
    {
        $result = Compositor::blend(BlendMode::Screen)->composite(
            Color::of(ColorSpaces::srgb(), [null, 0.5, 0.5], 1.0),
            Color::of(ColorSpaces::srgb(), [0.5, null, 0.5], 1.0),
        );

        foreach ([0.5, 0.5, 0.75] as $index => $value) {
            self::assertFloatClose($value, $result->coordinates()[$index] ?? \NAN, Tolerances::linearAlgebra(), (string) $index);
        }
    }

    public function testOpaqueBackdropFullyRealisesTheBlend(): void
    {
        $result = Compositor::blend(BlendMode::Screen)->composite(Color::fromSrgb(0.3, 0.3, 0.3), Color::fromSrgb(0.6, 0.6, 0.6));

        self::assertSame(1.0, $result->alpha());
        self::assertFloatClose(0.72, $result->coordinates()[0] ?? \NAN, Tolerances::linearAlgebra());
    }

    public function testTransparentBackdropLeavesTheSource(): void
    {
        $result = Compositor::blend(BlendMode::Difference)->composite(Color::fromSrgb(0.3, 0.5, 0.7, 0.5), Color::fromSrgb(1.0, 1.0, 1.0, 0.0));

        self::assertSame([0.3, 0.5, 0.7], $result->coordinates());
        self::assertSame(0.5, $result->alpha());
    }

    public function testClearGivesTransparentBlack(): void
    {
        $result = Compositor::porterDuff(PorterDuff::Clear)->composite(Color::fromHex('#f00'), Color::fromHex('#00f'));

        self::assertSame([0.0, 0.0, 0.0], $result->coordinates());
        self::assertSame(0.0, $result->alpha());
    }

    public function testDestinationKeepsTheBackdrop(): void
    {
        $result = Compositor::porterDuff(PorterDuff::Destination)->composite(Color::fromSrgb(1.0, 0.0, 0.0, 0.3), Color::fromSrgb(0.1, 0.2, 0.3, 0.6));

        self::assertSame([0.1, 0.2, 0.3], \array_map(static fn(?float $v): float => \round($v ?? \NAN, 12), $result->coordinates()));
        self::assertSame(0.6, $result->alpha());
    }

    public function testLighterSaturates(): void
    {
        $result = Compositor::porterDuff(PorterDuff::Lighter)->composite(Color::fromSrgb(0.6, 0.6, 0.6, 0.3), Color::fromSrgb(0.6, 0.6, 0.6, 0.3));

        self::assertSame(0.6, \round($result->alpha(), 12));
        self::assertFloatClose(0.6, $result->coordinates()[0] ?? \NAN, Tolerances::linearAlgebra());

        $saturated = Compositor::porterDuff(PorterDuff::Lighter)->composite(Color::fromSrgb(0.8, 0.8, 0.8), Color::fromSrgb(0.8, 0.8, 0.8));
        self::assertSame([1.0, 1.0, 1.0], $saturated->coordinates());
        self::assertSame(1.0, $saturated->alpha());
    }

    public function testOutOfGamutValuesAreNotClippedByPorterDuff(): void
    {
        $result = Compositor::porterDuff(PorterDuff::Copy)->composite(Color::fromSrgb(1.2, -0.1, 0.5), Color::fromHex('#fff'));

        self::assertSame([1.2, -0.1, 0.5], $result->coordinates());
    }

    public function testMissingValuesCountAsZero(): void
    {
        $result = Compositor::porterDuff(PorterDuff::SourceAtop)->composite(Color::of(ColorSpaces::srgb(), [null, 1.0, 1.0], 1.0), Color::fromHex('#fff'));

        self::assertSame([0.0, 1.0, 1.0], $result->coordinates());
    }

    public function testLinearLightVariant(): void
    {
        $operator = Compositor::sourceOver(ColorSpaces::srgbLinear());
        $result = $operator->composite(Color::fromSrgb(1.0, 1.0, 1.0, 0.5), Color::fromHex('#000'));

        self::assertSame('srgb-linear', $result->space()->id());
        self::assertSame(0.5, $result->coordinates()[0]);
        self::assertGreaterThan(0.73, $result->to(ColorSpaces::srgb())->coordinates()[0] ?? \NAN, 'half the light is brighter than sRGB 0.5');
    }

    public function testFactories(): void
    {
        self::assertInstanceOf(SourceOver::class, Compositor::sourceOver());
        self::assertInstanceOf(SourceOver::class, Compositor::porterDuff(PorterDuff::SourceOver));
        self::assertInstanceOf(SourceOver::class, Compositor::blend(BlendMode::Normal));
        self::assertInstanceOf(Composite::class, Compositor::blend(BlendMode::Normal, PorterDuff::Xor));
        self::assertSame('srgb', Compositor::blend(BlendMode::Multiply)->space()->id());

        $operator = new Composite(PorterDuff::SourceIn, BlendMode::Multiply, ColorSpaces::displayP3());
        self::assertSame('composite', $operator->id()->name);
        self::assertSame(1, $operator->id()->revision);
        self::assertSame('Compositing and Blending Level 1', $operator->id()->sources[0]->title);
        self::assertSame('operator=source-in,blend=multiply,space=display-p3', $operator->id()->variant);
        self::assertSame(PorterDuff::SourceIn, $operator->operator);
        self::assertSame(BlendMode::Multiply, $operator->blend);
    }

    public function testBlendModesNeedAnRgbSpace(): void
    {
        $this->expectException(InvalidCompositingSpaceException::class);
        $this->expectExceptionMessage('The blend mode "multiply" needs an RGB space; "oklab" is not one.');
        new Composite(PorterDuff::SourceOver, BlendMode::Multiply, ColorSpaces::oklab());
    }

    public function testNormalBlendingWorksInAnyRectangularSpace(): void
    {
        self::assertSame('oklab', (new Composite(PorterDuff::Xor, BlendMode::Normal, ColorSpaces::oklab()))->space()->id());
    }

    public function testHueSpacesAreRejected(): void
    {
        $this->expectException(InvalidCompositingSpaceException::class);
        $this->expectExceptionMessage('has a hue channel');
        new Composite(PorterDuff::Xor, BlendMode::Normal, ColorSpaces::hsl());
    }
}
