<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Contrast;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Compositing\LayerStack;
use Domm98CZ\Color\Contrast\Algorithm\Wcag2ContrastRatio;
use Domm98CZ\Color\Contrast\Algorithm\Wcag2LuminanceThreshold;
use Domm98CZ\Color\Contrast\Contrast;
use Domm98CZ\Color\Contrast\Exception\ColorOutsideSrgbException;
use Domm98CZ\Color\Contrast\Exception\EmptyBackgroundSetException;
use Domm98CZ\Color\Contrast\Exception\MissingBackdropException;
use Domm98CZ\Color\Contrast\Result\ContrastRange;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\TestCase;

final class Wcag2ContrastRatioTest extends TestCase
{
    use FloatAssertions;

    public function testKnownRatios(): void
    {
        $wcag = Contrast::wcag2();
        $white = Color::fromHex('#fff');

        self::assertSame(21.0, $wcag->measure(Color::fromHex('#000'), $white)->value);
        self::assertSame(1.0, $wcag->measure($white, $white)->value);
        // L(#777) = ((119/255 + 0.055) / 1.055)^2.4 = 0.18447; (1.05) / (0.18447 + 0.05) = 4.4781
        self::assertFloatClose(4.478089, $wcag->measure(Color::fromHex('#777'), $white)->value, Tolerance::absolute(Tolerances::printedDecimals(6)));
        self::assertSame($wcag->measure(Color::fromHex('#777'), $white)->value, $wcag->measure($white, Color::fromHex('#777'))->value, 'symmetric');
    }

    public function testRelativeLuminance(): void
    {
        $current = Contrast::wcag2();
        $legacy = Contrast::wcag2(Wcag2LuminanceThreshold::Wcag20);
        $gray = static fn(float $v): Color => Color::fromSrgb($v, $v, $v);
        $weighted = static fn(float $v): float => 0.2126 * $v + 0.7152 * $v + 0.0722 * $v;

        self::assertSame(1.0, \round($current->relativeLuminance($gray(1.0)), 12));
        self::assertSame(0.0, $current->relativeLuminance($gray(0.0)));
        self::assertSame($weighted(0.04 / 12.92), $current->relativeLuminance($gray(0.04)));
        self::assertSame($weighted(((0.04 + 0.055) / 1.055) ** 2.4), $legacy->relativeLuminance($gray(0.04)), 'between 0.03928 and 0.04045');
        self::assertSame($weighted(0.04045 / 12.92), $current->relativeLuminance($gray(0.04045)), 'the threshold itself is linear');
        self::assertSame($weighted(0.03928 / 12.92), $legacy->relativeLuminance($gray(0.03928)));
        self::assertSame(0.2126, $current->relativeLuminance(Color::fromSrgb(1.0, 0.0, 0.0)));
        self::assertSame(0.7152, $current->relativeLuminance(Color::fromSrgb(0.0, 1.0, 0.0)));
        self::assertSame(0.0722, $current->relativeLuminance(Color::fromSrgb(0.0, 0.0, 1.0)));
        self::assertSame(0.0, $current->relativeLuminance(Color::of(ColorSpaces::srgb(), [null, null, null])), 'missing = 0');
        self::assertFloatClose(0.2126, $current->relativeLuminance(Color::fromHsl(0, 100, 50)), Tolerances::linearAlgebra(), 'converted to sRGB');
    }

    public function testIdentity(): void
    {
        self::assertSame('wcag2-contrast-ratio[threshold=0.04045]@r1', Contrast::wcag2()->id()->identifier());
        self::assertSame('wcag2-contrast-ratio[threshold=0.03928]@r1', Contrast::wcag2(Wcag2LuminanceThreshold::Wcag20)->id()->identifier());
        self::assertSame('Web Content Accessibility Guidelines (WCAG) 2.2', Contrast::wcag2()->id()->sources[0]->title);
        self::assertSame('Web Content Accessibility Guidelines (WCAG) 2.0', Contrast::wcag2(Wcag2LuminanceThreshold::Wcag20)->id()->sources[0]->title);
        self::assertEquals(new Wcag2ContrastRatio(), Contrast::wcag2());
        self::assertSame(0.04045, Wcag2LuminanceThreshold::Current->threshold());
    }

    public function testOpaqueMeasurementCarriesTheColors(): void
    {
        $fg = Color::fromHex('#777');
        $bg = Color::fromHex('#fff');
        $measurement = Contrast::wcag2()->measure($fg, $bg);

        self::assertSame($fg, $measurement->foreground);
        self::assertSame($bg, $measurement->background);
        self::assertNull($measurement->foregroundComposition);
        self::assertNull($measurement->backgroundComposition);
        self::assertTrue($measurement->diagnostics->isEmpty());
        self::assertFalse($measurement->experimental);
    }

    public function testTranslucentForegroundIsComposited(): void
    {
        $bg = Color::fromHex('#fff');
        $measurement = Contrast::wcag2()->measure(Color::fromRgba(0, 0, 0, 0.5), $bg);

        self::assertNotNull($measurement->foregroundComposition);
        self::assertSame([0.5, 0.5, 0.5], $measurement->foreground->coordinates());
        self::assertSame(Contrast::wcag2()->measure(Color::fromSrgb(0.5, 0.5, 0.5), $bg)->value, $measurement->value);
        self::assertSame('translucent-foreground-composited', $measurement->diagnostics->assumptions[0]->code);
    }

    public function testLayerStackBackground(): void
    {
        $background = LayerStack::of(Color::fromRgba(255, 255, 255, 0.5))->over(Color::fromHex('#000'));
        $measurement = Contrast::wcag2()->measure(Color::fromHex('#000'), $background);

        self::assertNotNull($measurement->backgroundComposition);
        self::assertSame([0.5, 0.5, 0.5], $measurement->background->coordinates());
    }

    public function testTranslucentBackgroundNeedsABackdrop(): void
    {
        try {
            Contrast::wcag2()->measure(Color::fromHex('#000'), Color::fromRgba(255, 255, 255, 0.5));
            self::fail('A translucent background must be refused.');
        } catch (MissingBackdropException $exception) {
            self::assertSame(['opaque backdrop'], $exception->getMissing());
            self::assertStringContainsString('alpha 0.5', $exception->getMessage());
        }

        $this->expectException(MissingBackdropException::class);
        Contrast::wcag2()->measure(Color::fromHex('#000'), LayerStack::of(Color::fromRgba(255, 255, 255, 0.5)));
    }

    public function testColorsOutsideSrgbAreRefused(): void
    {
        $p3 = Color::of(ColorSpaces::displayP3(), [0.0, 1.0, 0.0]);

        try {
            Contrast::wcag2()->measure($p3, Color::fromHex('#000'));
            self::fail('Must not clip.');
        } catch (ColorOutsideSrgbException $exception) {
            self::assertSame($p3, $exception->getColor());
            self::assertStringContainsString('color(display-p3 0 1 0)', $exception->getMessage());
        }

        self::assertSame(21.0, Contrast::wcag2()->measure(Color::fromSrgb(1.0000009, 1.0, 1.0), Color::fromSrgb(-0.0000009, 0.0, 0.0))->value, 'within 1e-6 is clamped');
        self::assertSame(21.0, Contrast::wcag2()->measure(Color::fromSrgb(1.0 + 1e-6, 1.0, 1.0), Color::fromSrgb(-1e-6, 0.0, 0.0))->value, 'the tolerance is inclusive');
        $this->expectException(ColorOutsideSrgbException::class);
        Contrast::wcag2()->measure(Color::fromSrgb(0.5, 0.5, -0.0000011), Color::fromHex('#000'));
    }

    public function testContrastRange(): void
    {
        $text = Color::fromHex('#777');
        // #777 against: #eee 3.86, #fff 4.48, #000 4.69, #000 4.69
        $range = ContrastRange::measure(Contrast::wcag2(), $text, [Color::fromHex('#eee'), Color::fromHex('#fff'), Color::fromHex('#000'), Color::fromHex('#000')]);

        self::assertCount(4, $range->measurements);
        self::assertSame($range->measurements[0], $range->min);
        self::assertSame($range->measurements[2], $range->max, 'the first of equal values wins');
        self::assertSame(Contrast::wcag2()->measure($text, Color::fromHex('#eee'))->value, $range->measurements[0]->value);

        $tie = ContrastRange::measure(Contrast::wcag2(), $text, [Color::fromHex('#fff'), Color::fromHex('#eee'), Color::fromHex('#eee')]);
        self::assertSame($tie->measurements[1], $tie->min, 'the first of equal minima wins');

        $single = ContrastRange::measure(Contrast::wcag2(), $text, [Color::fromHex('#fff')]);
        self::assertSame($single->min, $single->max);

        $this->expectException(EmptyBackgroundSetException::class);
        ContrastRange::measure(Contrast::wcag2(), $text, []);
    }
}
