<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Contrast;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Compositing\LayerStack;
use Domm98CZ\Color\Contrast\Contract\ContrastAlgorithm;
use Domm98CZ\Color\Contrast\Contrast;
use Domm98CZ\Color\Contrast\ContrastAdjuster;
use Domm98CZ\Color\Contrast\Exception\ColorOutsideSrgbException;
use Domm98CZ\Color\Contrast\Exception\MissingBackdropException;
use Domm98CZ\Color\Contrast\Result\ContrastMeasurement;
use Domm98CZ\Color\Gamut\GamutMappers;
use Domm98CZ\Color\Math\Exception\NonFiniteValueException;
use Domm98CZ\Color\Operation\LightnessModel;
use Domm98CZ\Color\Operation\Quantizer;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Provenance\Diagnostics;
use Domm98CZ\Color\Provenance\SourceKind;
use Domm98CZ\Color\Provenance\WarningCode;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Record\Oklch;
use PHPUnit\Framework\TestCase;

final class ContrastAdjusterTest extends TestCase
{
    private const string WHITE = '#ffffff';

    public function testIdentity(): void
    {
        $default = new ContrastAdjuster(Contrast::wcag2(), LightnessModel::OklchL);

        self::assertSame('contrast-lightness-search[lightness=oklch-l,bits=8]@r1', $default->id()->identifier());
        self::assertSame(SourceKind::Library, $default->id()->sources[0]->kind);
        self::assertSame('contrast-lightness-search[lightness=oklch-l,bits=none]@r1', (new ContrastAdjuster(Contrast::wcag2(), LightnessModel::OklchL, GamutMappers::cssColor4(), null))->id()->identifier());
        self::assertSame('contrast-lightness-search[lightness=hsl-l,bits=16]@r1', (new ContrastAdjuster(Contrast::wcag2(), LightnessModel::HslL, GamutMappers::clip(), new Quantizer(16)))->id()->identifier());
        self::assertSame('contrast-target-unreachable', WarningCode::ContrastTargetUnreachable->value);
        self::assertStringContainsString('target', WarningCode::ContrastTargetUnreachable->description());
    }

    public function testInputThatPassesIsReturnedFitted(): void
    {
        $blue = Color::fromHex('#3366cc');
        $result = self::adjuster()->adjustForeground($blue, Color::fromHex(self::WHITE), 4.5);

        self::assertTrue($result->reachable);
        self::assertSame(0.0, $result->lightnessChange);
        self::assertSame('#3366cc', $result->color->toHex());
        self::assertSame($blue, $result->original);
        self::assertTrue($result->mapping->wasInGamut);
        self::assertSame(Contrast::wcag2()->measure($blue, Color::fromHex(self::WHITE))->value, $result->contrast->value);
        self::assertSame(4.5, $result->target);
        self::assertSame(LightnessModel::OklchL, $result->lightness);
        self::assertSame('contrast-lightness-search[lightness=oklch-l,bits=8]@r1', $result->algorithm->identifier());
        self::assertTrue($result->diagnostics->isEmpty());

        self::assertSame(0.0, self::adjuster()->adjustForeground(Color::fromHex('#777777'), Color::fromHex('#777777'), 1.0)->lightnessChange, 'a target of 1 is met by any color');
    }

    public function testDarkerTextReachesTheTargetKeepingTheHue(): void
    {
        $result = self::adjuster()->adjustForeground(Color::fromHex('#3366cc'), Color::fromHex(self::WHITE), 7.0);

        self::assertTrue($result->reachable);
        self::assertGreaterThanOrEqual(7.0, $result->contrast->value);
        self::assertLessThan(0.0, $result->lightnessChange, 'white text on white cannot pass, so the text gets darker');
        self::assertTrue($result->color->isInGamut(ColorSpaces::srgb()));
        self::assertEqualsWithDelta(Color::fromHex('#3366cc')->in(Oklch::class)->h ?? \NAN, $result->color->in(Oklch::class)->h ?? \NAN, 5.0, 'hue kept (quantisation moves it by well under a degree at this chroma)');
        self::assertSame(Contrast::wcag2()->measure(Color::fromHex($result->color->toHex()), Color::fromHex(self::WHITE))->value, $result->contrast->value, 'the ratio of the written hex is the ratio reported');
        self::assertTrue($result->diagnostics->isEmpty());
    }

    public function testTheNearerDirectionWins(): void
    {
        // #767676 reaches 4.5 both near black (Y <= 0.0014) and near white (Y >= 0.99); L0 of #3366cc is 0.52, black is nearer.
        $result = self::adjuster()->adjustForeground(Color::fromHex('#3366cc'), Color::fromHex('#767676'), 4.5);

        self::assertTrue($result->reachable);
        self::assertLessThan(0.0, $result->lightnessChange);
        self::assertLessThan(0.002, Contrast::wcag2()->relativeLuminance($result->color));
    }

    public function testUnreachableTargetReturnsTheBestExtreme(): void
    {
        // #767676: white gives 4.54, black 4.62; neither reaches 7.
        $result = self::adjuster()->adjustForeground(Color::fromHex('#3366cc'), Color::fromHex('#767676'), 7.0);

        self::assertFalse($result->reachable);
        self::assertSame('#000000', $result->color->toHex());
        self::assertEqualsWithDelta(4.62, $result->contrast->value, 0.01);
        self::assertTrue($result->diagnostics->hasWarning(WarningCode::ContrastTargetUnreachable));
        self::assertSame(['target' => 7.0, 'contrast' => $result->contrast->value], $result->diagnostics->warnings[0]->context);
        self::assertEqualsWithDelta(-0.52, $result->lightnessChange, 0.02);
    }

    public function testTiesGoTowardsBlack(): void
    {
        $extremesOnly = new class implements ContrastAlgorithm {
            public function id(): AlgorithmId
            {
                return new AlgorithmId('test-extremes-only');
            }

            public function measure(Color $foreground, Color|LayerStack $background): ContrastMeasurement
            {
                // Exactly black (luminance 0) or white (1 up to round-off) passes, every gray fails.
                $y = Contrast::wcag2()->relativeLuminance($foreground);
                $background = $background instanceof LayerStack ? $background->composite()->color : $background;

                return new ContrastMeasurement($y <= 0.0 || $y >= 1.0 - 1e-12 ? 21.0 : 1.0, $this->id(), $foreground, $background, null, null, Diagnostics::none());
            }
        };
        // Unrounded candidates: with 8-bit quantisation every dark gray would become black and pass early.
        $adjuster = new ContrastAdjuster($extremesOnly, LightnessModel::OklchL, GamutMappers::cssColor4(), null);
        $gray = Color::of(ColorSpaces::oklch(), [0.5, 0.0, null]);

        $passing = $adjuster->adjustForeground($gray, Color::fromHex(self::WHITE), 4.5);
        self::assertTrue($passing->reachable);
        self::assertSame('#000000', $passing->color->toHex(), 'both extremes pass with |change| 0.5: black wins');
        self::assertSame(-0.5, $passing->lightnessChange);

        $failing = $adjuster->adjustForeground($gray, Color::fromHex(self::WHITE), 22.0);
        self::assertFalse($failing->reachable);
        self::assertSame('#000000', $failing->color->toHex(), 'both extremes fail with the same value: black wins');
    }

    public function testBackgroundAdjustmentMirrorsForegroundAdjustmentForOpaqueColors(): void
    {
        $tint = Color::fromOklch(0.97, 0.03, 262.3);
        $text = Color::fromHex('#1a1a1a');
        $background = self::adjuster()->adjustBackground($tint, $text, 7.0);
        $asForeground = self::adjuster()->adjustForeground($tint, $text, 7.0);

        self::assertTrue($background->reachable);
        self::assertSame(0.0, $background->lightnessChange, 'a light tint on near-black passes as it is');
        self::assertSame($text, $background->contrast->foreground);
        self::assertTrue($background->color->equals($asForeground->color), 'WCAG 2 is symmetric');
        self::assertSame($background->contrast->value, $asForeground->contrast->value);

        $darker = self::adjuster()->adjustBackground(Color::fromHex('#3366cc'), Color::fromHex(self::WHITE), 7.0);
        self::assertTrue($darker->color->equals(self::adjuster()->adjustForeground(Color::fromHex('#3366cc'), Color::fromHex(self::WHITE), 7.0)->color));
    }

    public function testWideGamutInputIsMapped(): void
    {
        $result = self::adjuster()->adjustForeground(Color::parse('color(display-p3 1 0 0)'), Color::fromHex(self::WHITE), 1.0);

        self::assertFalse($result->mapping->wasInGamut);
        self::assertSame('#ff0b0c', $result->color->toHex());
        self::assertSame(0.0, $result->lightnessChange);
    }

    public function testLightnessOutsideTheModelRangeIsClamped(): void
    {
        $result = self::adjuster()->adjustForeground(Color::fromOklch(1.1, 0.2, 90.0), Color::fromHex(self::WHITE), 4.5);

        self::assertTrue($result->reachable);
        self::assertGreaterThanOrEqual(-1.0, $result->lightnessChange);
        self::assertLessThanOrEqual(0.0, $result->lightnessChange);
        self::assertGreaterThanOrEqual(4.5, $result->contrast->value);
    }

    public function testTranslucentForegroundIsKeptAndComposited(): void
    {
        // 50 % black over white is #808080 at best (3.95:1): 7:1 is unreachable, and the alpha stays.
        $result = self::adjuster()->adjustForeground(Color::fromRgba(0, 0, 0, 0.5), Color::fromHex(self::WHITE), 7.0);

        self::assertFalse($result->reachable);
        self::assertEqualsWithDelta(0.5, $result->color->alpha(), 0.5 / 255, 'the alpha is kept, quantised with the color (128/255)');
        self::assertNotNull($result->contrast->foregroundComposition);
        self::assertSame('translucent-foreground-composited', $result->diagnostics->assumptions[0]->code);
        self::assertTrue($result->diagnostics->hasWarning(WarningCode::ContrastTargetUnreachable));

        $faint = self::adjuster()->adjustForeground(Color::fromRgba(0, 0, 0, 0.01), Color::fromHex(self::WHITE), 4.5);
        self::assertFalse($faint->reachable);
        self::assertEqualsWithDelta(0.01, $faint->color->alpha(), 0.5 / 255, 'the alpha is quantised with the color');
    }

    public function testLayerStackBackground(): void
    {
        $stack = LayerStack::of(Color::fromRgba(255, 255, 255, 0.5))->over(Color::fromHex('#000000'));
        $result = self::adjuster()->adjustForeground(Color::fromHex('#3366cc'), $stack, 4.5);

        self::assertTrue($result->reachable);
        self::assertNotNull($result->contrast->backgroundComposition);
        self::assertSame([0.5, 0.5, 0.5], $result->contrast->background->coordinates());
    }

    public function testTheFixedSideFollowsTheRulesOfTheMeasurement(): void
    {
        $adjuster = self::adjuster();

        try {
            $adjuster->adjustForeground(Color::fromHex('#000000'), Color::fromRgba(255, 255, 255, 0.5), 4.5);
            self::fail('A translucent fixed background must be refused.');
        } catch (MissingBackdropException) {
        }

        try {
            $adjuster->adjustBackground(Color::fromRgba(255, 255, 255, 0.5), Color::fromHex('#000000'), 4.5);
            self::fail('A translucent background to adjust has nothing behind it.');
        } catch (MissingBackdropException) {
        }

        try {
            $adjuster->adjustForeground(Color::fromHex('#000000'), Color::of(ColorSpaces::displayP3(), [0.0, 1.0, 0.0]), 4.5);
            self::fail('A fixed color outside sRGB is not mapped.');
        } catch (ColorOutsideSrgbException) {
        }

        $this->expectException(NonFiniteValueException::class);
        $adjuster->adjustForeground(Color::fromHex('#000000'), Color::fromHex(self::WHITE), \NAN);
    }

    public function testWithoutQuantizerTheResultIsUnrounded(): void
    {
        $exact = new ContrastAdjuster(Contrast::wcag2(), LightnessModel::OklchL, GamutMappers::cssColor4(), null);
        $result = $exact->adjustForeground(Color::fromHex('#3366cc'), Color::fromHex(self::WHITE), 7.0);

        self::assertSame('contrast-lightness-search[lightness=oklch-l,bits=none]@r1', $result->algorithm->identifier());
        self::assertGreaterThanOrEqual(7.0, $result->contrast->value);
        self::assertLessThan(7.0 + 1e-3, $result->contrast->value, 'the boundary is found to 1e-6 of the lightness range');
        self::assertTrue($result->color->isInGamut(ColorSpaces::srgb()));
    }

    private static function adjuster(): ContrastAdjuster
    {
        return new ContrastAdjuster(Contrast::wcag2(), LightnessModel::OklchL);
    }
}
