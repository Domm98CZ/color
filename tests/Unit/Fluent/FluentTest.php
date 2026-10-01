<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Fluent;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Contrast\Algorithm\Wcag2LuminanceThreshold;
use Domm98CZ\Color\Contrast\Contrast;
use Domm98CZ\Color\Contrast\Exception\ColorOutsideSrgbException;
use Domm98CZ\Color\Fluent\Fluent;
use Domm98CZ\Color\Fluent\FluentColor;
use Domm98CZ\Color\Fluent\FluentConfig;
use Domm98CZ\Color\Gamut\GamutMappers;
use Domm98CZ\Color\Harmony\Harmony;
use Domm98CZ\Color\Harmony\HarmonyScheme;
use Domm98CZ\Color\Harmony\HueWheels;
use Domm98CZ\Color\Interpolation\HueInterpolation;
use Domm98CZ\Color\Notation\CssFormat;
use Domm98CZ\Color\Notation\Exception\ColorParseException;
use Domm98CZ\Color\Notation\Exception\OutOfGamutException;
use Domm98CZ\Color\Operation\Adjuster;
use Domm98CZ\Color\Operation\ChromaModel;
use Domm98CZ\Color\Operation\Exception\InvalidOperationException;
use Domm98CZ\Color\Operation\HueModel;
use Domm98CZ\Color\Operation\LightnessModel;
use Domm98CZ\Color\Operation\Mixer;
use Domm98CZ\Color\Operation\TintShadeTone;
use Domm98CZ\Color\Provenance\WarningCode;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every fluent method gives the same result as the direct call of the service with the same
 * configuration.
 */
final class FluentTest extends TestCase
{
    private const string BASE = '#3366cc';

    public function testDefaults(): void
    {
        $config = FluentConfig::defaults();

        self::assertSame(LightnessModel::OklchL, $config->lightness);
        self::assertSame(ChromaModel::Oklch, $config->chroma);
        self::assertSame(HueModel::Oklch, $config->hue);
        self::assertSame('oklab', $config->mixer->method->space->id());
        self::assertSame(HueInterpolation::Shorter, $config->mixer->method->hue);
        self::assertSame('hue-wheel-oklch@r1', $config->wheel->id()->identifier());
        self::assertSame('srgb', $config->outputSpace->id());
        self::assertSame(GamutMappers::cssColor4()->id()->identifier(), $config->outputMapper?->id()->identifier());
        self::assertSame(Contrast::wcag2()->id()->identifier(), $config->contrast->id()->identifier());
        self::assertEquals($config, Fluent::create()->config());
    }

    public function testWithersChangeOneSettingAndKeepTheOriginal(): void
    {
        $defaults = FluentConfig::defaults();
        $mixer = new Mixer(ColorSpaces::srgb());
        $contrast = Contrast::wcag2(Wcag2LuminanceThreshold::Wcag20);

        self::assertSame(LightnessModel::HslL, $defaults->withLightness(LightnessModel::HslL)->lightness);
        self::assertSame(ChromaModel::CieLch, $defaults->withChroma(ChromaModel::CieLch)->chroma);
        self::assertSame(HueModel::Hsl, $defaults->withHue(HueModel::Hsl)->hue);
        self::assertSame($mixer, $defaults->withMixer($mixer)->mixer);
        self::assertSame('hue-wheel-hsl@r1', $defaults->withWheel(HueWheels::hsl())->wheel->id()->identifier());
        self::assertSame('display-p3', $defaults->withOutputSpace(ColorSpaces::displayP3())->outputSpace->id());
        self::assertNull($defaults->withOutputMapping(null)->outputMapper);
        self::assertSame($contrast, $defaults->withContrast($contrast)->contrast);

        $changed = $defaults->withLightness(LightnessModel::HslL);
        self::assertSame(LightnessModel::OklchL, $defaults->lightness, 'immutable');
        self::assertEquals([$defaults->chroma, $defaults->hue, $defaults->mixer, $defaults->wheel, $defaults->outputSpace, $defaults->outputMapper, $defaults->contrast], [$changed->chroma, $changed->hue, $changed->mixer, $changed->wheel, $changed->outputSpace, $changed->outputMapper, $changed->contrast]);
    }

    /**
     * @return iterable<string, array{\Closure(FluentColor): FluentColor, \Closure(Color): Color}>
     */
    public static function steps(): iterable
    {
        $adjuster = new Adjuster();
        $oklab = new TintShadeTone(ColorSpaces::oklab());
        $srgb = new Mixer(ColorSpaces::srgb(), HueInterpolation::Longer);

        yield 'lighten' => [static fn(FluentColor $f) => $f->lighten(0.08), static fn(Color $c) => $adjuster->shiftLightness($c, 0.08, LightnessModel::OklchL)];
        yield 'lighten in HSL' => [static fn(FluentColor $f) => $f->lighten(10.0, LightnessModel::HslL), static fn(Color $c) => $adjuster->shiftLightness($c, 10.0, LightnessModel::HslL)];
        yield 'darken' => [static fn(FluentColor $f) => $f->darken(0.1), static fn(Color $c) => $adjuster->shiftLightness($c, -0.1, LightnessModel::OklchL)];
        yield 'darken in CIE L*' => [static fn(FluentColor $f) => $f->darken(5.0, LightnessModel::CieLabL), static fn(Color $c) => $adjuster->shiftLightness($c, -5.0, LightnessModel::CieLabL)];
        yield 'scaleChroma' => [static fn(FluentColor $f) => $f->scaleChroma(1.2), static fn(Color $c) => $adjuster->scaleChroma($c, 1.2, ChromaModel::Oklch)];
        yield 'scaleChroma in CIE LCh' => [static fn(FluentColor $f) => $f->scaleChroma(0.5, ChromaModel::CieLch), static fn(Color $c) => $adjuster->scaleChroma($c, 0.5, ChromaModel::CieLch)];
        yield 'rotateHue' => [static fn(FluentColor $f) => $f->rotateHue(30.0), static fn(Color $c) => $adjuster->rotateHue($c, 30.0, HueModel::Oklch)];
        yield 'rotateHue in HSL' => [static fn(FluentColor $f) => $f->rotateHue(180.0, HueModel::Hsl), static fn(Color $c) => $adjuster->rotateHue($c, 180.0, HueModel::Hsl)];
        yield 'mix' => [static fn(FluentColor $f) => $f->mix('#ffcc00', 0.25), static fn(Color $c) => (new Mixer())->mix($c, Color::fromHex('#ffcc00'), 0.25)];
        yield 'mix half' => [static fn(FluentColor $f) => $f->mix(Color::fromHex('#ffcc00')), static fn(Color $c) => (new Mixer())->mix($c, Color::fromHex('#ffcc00'))];
        yield 'mix with a mixer' => [static fn(FluentColor $f) => $f->mix('#ffcc00', 0.25, $srgb), static fn(Color $c) => $srgb->mix($c, Color::fromHex('#ffcc00'), 0.25)];
        yield 'tint' => [static fn(FluentColor $f) => $f->tint(0.3), static fn(Color $c) => $oklab->tint($c, 0.3)];
        yield 'shade' => [static fn(FluentColor $f) => $f->shade(0.3), static fn(Color $c) => $oklab->shade($c, 0.3)];
        yield 'tone' => [static fn(FluentColor $f) => $f->tone(0.3), static fn(Color $c) => $oklab->tone($c, 0.3)];
        yield 'tone with a gray' => [static fn(FluentColor $f) => $f->tone(0.3, '#777'), static fn(Color $c) => $oklab->tone($c, 0.3, Color::fromHex('#777'))];
        yield 'tint with a mixer' => [static fn(FluentColor $f) => $f->tint(0.3, $srgb), static fn(Color $c) => (new TintShadeTone(ColorSpaces::srgb(), HueInterpolation::Longer))->tint($c, 0.3)];
        yield 'shade with a mixer' => [static fn(FluentColor $f) => $f->shade(0.3, $srgb), static fn(Color $c) => (new TintShadeTone(ColorSpaces::srgb(), HueInterpolation::Longer))->shade($c, 0.3)];
        yield 'tone with a mixer' => [static fn(FluentColor $f) => $f->tone(0.3, null, $srgb), static fn(Color $c) => (new TintShadeTone(ColorSpaces::srgb(), HueInterpolation::Longer))->tone($c, 0.3)];
        yield 'withAlpha' => [static fn(FluentColor $f) => $f->withAlpha(0.4), static fn(Color $c) => $c->withAlpha(0.4)];
        yield 'missing alpha' => [static fn(FluentColor $f) => $f->withAlpha(null), static fn(Color $c) => $c->withAlpha(null)];
        yield 'complement' => [static fn(FluentColor $f) => $f->complement(), static fn(Color $c) => Harmony::complementary($c, HueWheels::oklch())->members[1]->color];
        yield 'complement on the RYB wheel' => [static fn(FluentColor $f) => $f->complement(HueWheels::ryb()), static fn(Color $c) => Harmony::complementary($c, HueWheels::ryb())->members[1]->color];
    }

    /**
     * @param \Closure(FluentColor): FluentColor $fluent
     * @param \Closure(Color): Color $direct
     */
    #[DataProvider('steps')]
    public function testEveryStepEqualsTheDirectCall(\Closure $fluent, \Closure $direct): void
    {
        $base = Color::fromHex(self::BASE);
        $chain = $fluent(Fluent::create()->color(self::BASE));

        self::assertTrue($chain->color()->equals($direct($base)), $chain->color()->toCss() . ' vs ' . $direct($base)->toCss());
        self::assertTrue($chain->toColor()->equals(GamutMappers::cssColor4()->map($direct($base), ColorSpaces::srgb())->color));
    }

    public function testTheConfigurationDecidesTheMethod(): void
    {
        $config = FluentConfig::defaults()
            ->withLightness(LightnessModel::HslL)
            ->withChroma(ChromaModel::CieLch)
            ->withHue(HueModel::Hsl)
            ->withMixer(new Mixer(ColorSpaces::srgb()))
            ->withWheel(HueWheels::hsl());
        $chain = (new Fluent($config))->color(self::BASE)->lighten(10.0)->scaleChroma(0.8)->rotateHue(20.0)->mix('#ffcc00', 0.2)->tint(0.1)->complement();

        $adjuster = new Adjuster();
        $expected = $adjuster->rotateHue($adjuster->scaleChroma($adjuster->shiftLightness(Color::fromHex(self::BASE), 10.0, LightnessModel::HslL), 0.8, ChromaModel::CieLch), 20.0, HueModel::Hsl);
        $expected = (new TintShadeTone(ColorSpaces::srgb()))->tint((new Mixer(ColorSpaces::srgb()))->mix($expected, Color::fromHex('#ffcc00'), 0.2), 0.1);
        $expected = Harmony::complementary($expected, HueWheels::hsl())->members[1]->color;

        self::assertTrue($chain->color()->equals($expected));
        self::assertSame(
            [['by' => 10.0, 'model' => 'hsl-l'], ['scale' => 0.8, 'model' => 'cielch'], ['degrees' => 20.0, 'model' => 'hsl'], ['with' => 'rgb(255, 204, 0)', 'weight' => 0.2, 'space' => 'srgb', 'hue' => 'shorter'], ['amount' => 0.1, 'space' => 'srgb', 'hue' => 'shorter'], ['wheel' => 'hue-wheel-hsl@r1']],
            \array_map(static fn($step): array => $step->parameters, \array_slice($chain->trace()->steps, 1)),
        );
    }

    public function testTrace(): void
    {
        $chain = Fluent::create()->color('#3366cc')->lighten(0.3)->scaleChroma(1.5)->tone(0.1, '#808080');
        $trace = $chain->trace();

        self::assertSame(['color', 'lighten', 'scaleChroma', 'tone'], $trace->operations());
        self::assertSame(['css' => '#3366cc'], $trace->steps[0]->parameters);
        self::assertTrue($trace->steps[0]->result->equals(Color::fromHex('#3366cc')));
        self::assertSame(['by' => 0.3, 'model' => 'oklch-l'], $trace->steps[1]->parameters);
        self::assertSame(['amount' => 0.1, 'gray' => 'rgb(128, 128, 128)', 'space' => 'oklab', 'hue' => 'shorter'], $trace->steps[3]->parameters);
        self::assertSame($chain->color(), $trace->steps[3]->result);
        self::assertNull($trace->steps[1]->algorithm);
        self::assertTrue($trace->steps[1]->diagnostics->isEmpty());

        self::assertSame('srgb', $trace->outputSpace->id());
        self::assertNotNull($trace->output);
        self::assertFalse($trace->output->wasInGamut, 'OKLCh L 0.3 higher and chroma × 1.5 leave sRGB');
        self::assertSame($chain->color(), $trace->output->original);
        self::assertTrue($trace->output->color->equals($chain->toColor()));
        self::assertSame(GamutMappers::cssColor4()->id()->identifier(), $trace->output->method->identifier());

        self::assertSame(['space' => 'oklch'], Fluent::create()->color(Color::fromOklch(0.5, 0.1, 30.0))->trace()->steps[0]->parameters);
    }

    public function testParametersOfEveryStep(): void
    {
        $steps = Fluent::create()->color(self::BASE)
            ->darken(0.1)->shade(0.2)->withAlpha(0.5)->rotateHue(15.0)->mix('#fff')->tint(0.1)->complement()
            ->trace()->steps;

        self::assertSame([
            ['by' => 0.1, 'model' => 'oklch-l'],
            ['amount' => 0.2, 'space' => 'oklab', 'hue' => 'shorter'],
            ['alpha' => 0.5],
            ['degrees' => 15.0, 'model' => 'oklch'],
            ['with' => 'rgb(255, 255, 255)', 'weight' => 0.5, 'space' => 'oklab', 'hue' => 'shorter'],
            ['amount' => 0.1, 'space' => 'oklab', 'hue' => 'shorter'],
            ['wheel' => 'hue-wheel-oklch@r1'],
        ], \array_map(static fn($step): array => $step->parameters, \array_slice($steps, 1)));
    }

    public function testChainsAreImmutable(): void
    {
        $start = Fluent::create()->color(self::BASE);
        $lighter = $start->lighten(0.1);

        self::assertNotSame($start, $lighter);
        self::assertSame(['color'], $start->trace()->operations());
        self::assertSame(['color', 'lighten'], $lighter->trace()->operations());
        self::assertSame($start->config(), $lighter->config());
    }

    public function testComplementOfAGrayKeepsTheColorAndWarns(): void
    {
        $step = Fluent::create()->color('#808080')->complement()->trace()->steps[1];

        self::assertTrue($step->result->equals(Color::fromHex('#808080')));
        self::assertTrue($step->diagnostics->hasWarning(WarningCode::HueUndefined));
        self::assertSame('hue-wheel-oklch@r1', $step->algorithm?->identifier());
    }

    public function testOutput(): void
    {
        $vivid = Fluent::create()->color('oklch(0.7 0.4 150)');

        self::assertSame('oklch', $vivid->color()->space()->id(), 'intermediate colors are never mapped');
        self::assertSame(GamutMappers::cssColor4()->map($vivid->color(), ColorSpaces::srgb())->color->toHex(), $vivid->toHex());
        self::assertSame('#3366cc80', Fluent::create()->color('#3366cc80')->toHex());
        self::assertSame('#3366ccff', Fluent::create()->color(self::BASE)->toHex(true));
        self::assertSame('rgb(51, 102, 204)', Fluent::create()->color(self::BASE)->toCss());
        self::assertSame(Color::fromHex(self::BASE)->toCss(new CssFormat(2)), Fluent::create()->color(self::BASE)->toCss(new CssFormat(2)));

        $p3 = (new Fluent(FluentConfig::defaults()->withOutputSpace(ColorSpaces::displayP3())))->color('oklch(0.7 0.4 150)');
        self::assertSame('display-p3', $p3->toColor()->space()->id());
        self::assertTrue($p3->toColor()->isInGamut());
        self::assertStringStartsWith('color(display-p3 ', $p3->toCss());
    }

    public function testStrictModeOnlyConverts(): void
    {
        $strict = (new Fluent(FluentConfig::defaults()->withOutputMapping(null)))->color('oklch(0.7 0.4 150)');

        self::assertNull($strict->trace()->output);
        self::assertTrue($strict->toColor()->equals($strict->color()->to(ColorSpaces::srgb())));
        self::assertStringStartsWith('color(srgb ', $strict->toCss(), 'lossless form for a color outside sRGB');

        $this->expectException(OutOfGamutException::class);
        $strict->toHex();
    }

    public function testContrastWith(): void
    {
        $fluent = Fluent::create();
        $mapper = GamutMappers::cssColor4();
        $gray = $mapper->map(Color::fromHex('#777777'), ColorSpaces::srgb())->color;

        self::assertEquals(Contrast::wcag2()->measure($gray, $mapper->map(Color::fromHex('#ffffff'), ColorSpaces::srgb())->color), $fluent->color('#777')->contrastWith('#fff'));
        self::assertEqualsWithDelta(4.478089, $fluent->color('#777')->contrastWith('#fff')->value, Tolerances::printedDecimals(6));
        self::assertEquals(
            Contrast::wcag2(Wcag2LuminanceThreshold::Wcag20)->measure($gray, $mapper->map(Color::fromHex('#000000'), ColorSpaces::srgb())->color),
            $fluent->color('#777')->contrastWith(Color::fromHex('#000'), Contrast::wcag2(Wcag2LuminanceThreshold::Wcag20)),
        );

        $vivid = $fluent->color('oklch(0.7 0.4 150)');
        self::assertEquals(
            Contrast::wcag2()->measure($mapper->map($vivid->color(), ColorSpaces::srgb())->color, $mapper->map(Color::parse('lab(50 100 0)'), ColorSpaces::srgb())->color),
            $vivid->contrastWith('lab(50 100 0)'),
            'both colors are measured as displayed (mapped into the output space)',
        );

        $this->expectException(ColorOutsideSrgbException::class);
        (new Fluent(FluentConfig::defaults()->withOutputMapping(null)))->color('oklch(0.7 0.4 150)')->contrastWith('#fff');
    }

    public function testInvalidArgumentsAreRejectedByTheServices(): void
    {
        try {
            Fluent::create()->color('#3366cc')->mix('#ffcc00', 1.5);
            self::fail('weight 1.5 accepted');
        } catch (InvalidOperationException $e) {
            self::assertStringContainsString('weight', $e->getMessage());
        }

        $this->expectException(ColorParseException::class);
        Fluent::create()->color('not a color');
    }

    public function testHarmony(): void
    {
        $chain = Fluent::create()->color(self::BASE);

        self::assertEquals(Harmony::of(Color::fromHex(self::BASE), HueWheels::oklch(), HarmonyScheme::triadic(), ColorSpaces::srgb()), $chain->harmony(HarmonyScheme::triadic()));
        self::assertEquals(Harmony::of(Color::fromHex(self::BASE), HueWheels::ryb(), HarmonyScheme::square(), ColorSpaces::srgb()), $chain->harmony(HarmonyScheme::square(), HueWheels::ryb()));
        self::assertSame('display-p3', (new Fluent(FluentConfig::defaults()->withOutputSpace(ColorSpaces::displayP3())))->color(self::BASE)->harmony(HarmonyScheme::triadic())->gamut->id());
    }
}
