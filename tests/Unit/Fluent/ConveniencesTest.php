<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Fluent;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Fluent\HexColor;
use Domm98CZ\Color\Harmony\Harmony;
use Domm98CZ\Color\Harmony\HueWheels;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Operation\Exception\InvalidOperationException;
use Domm98CZ\Color\Operation\TintShadeTone;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\TestCase;

/**
 * The convenience methods of the fluent layer give exactly the results of the services they wrap.
 */
final class ConveniencesTest extends TestCase
{
    use FloatAssertions;

    private const string BASE = '#3366cc';

    public function testLuminance(): void
    {
        self::assertSame(0.0, (new HexColor('#000000'))->luminance());
        self::assertFloatClose(1.0, (new HexColor('#ffffff'))->luminance(), Tolerances::linearAlgebra());
        self::assertFloatClose(0.1845, (new HexColor('#777777'))->luminance(), Tolerance::absolute(Tolerances::printedDecimals(4)));
    }

    public function testDarkAndLight(): void
    {
        self::assertTrue((new HexColor('#000000'))->isDark());
        self::assertFalse((new HexColor('#ffffff'))->isDark());
        self::assertTrue((new HexColor('#ffffff'))->isLight());
        self::assertTrue((new HexColor(self::BASE))->isDark());
        self::assertFalse((new HexColor('#777777'))->isDark(), 'luminance 0.1845 is above the default threshold');
        self::assertTrue((new HexColor('#777777'))->isDark(0.2), 'the threshold is a parameter');
        self::assertFalse((new HexColor('#777777'))->isLight(0.2));
        self::assertFalse((new HexColor('#777777'))->isDark(0.1));
    }

    public function testTintsShadesAndTonesAreTheStepsOfTheMixer(): void
    {
        $base = Color::fromHex(self::BASE);
        $chain = new HexColor(self::BASE);
        $service = new TintShadeTone(ColorSpaces::oklab());

        $tints = $chain->tints(3);
        self::assertCount(3, $tints);
        foreach ($tints as $k => $tint) {
            self::assertTrue($tint->color()->equals($service->tint($base, ($k + 1) / 4)), 'tint ' . $k);
            self::assertSame('tint', $tint->trace()->steps[1]->operation);
        }

        $shades = $chain->shades(3);
        self::assertCount(3, $shades);
        foreach ($shades as $k => $shade) {
            self::assertTrue($shade->color()->equals($service->shade($base, ($k + 1) / 4)), 'shade ' . $k);
        }

        $tones = $chain->tones(2);
        self::assertCount(2, $tones);
        foreach ($tones as $k => $tone) {
            self::assertTrue($tone->color()->equals($service->tone($base, ($k + 1) / 3)), 'tone ' . $k);
        }

        self::assertTrue($chain->tones(1, '#777')[0]->color()->equals($service->tone($base, 0.5, Color::fromHex('#777'))));
        self::assertTrue($chain->tones(1, new HexColor('#777'))[0]->color()->equals($service->tone($base, 0.5, Color::fromHex('#777'))), 'a chain is accepted where a color is');
    }

    public function testChainsAreAcceptedAsArguments(): void
    {
        $base = new HexColor(self::BASE);
        $other = new HexColor('#ffcc00');

        self::assertTrue($base->mix($other, 0.25)->color()->equals($base->mix('#ffcc00', 0.25)->color()));
        self::assertSame($base->contrastWith('#ffffff')->value, $base->contrastWith(new HexColor('#ffffff'))->value);
        self::assertTrue($base->mix($other->lighten(0.5), 0.25)->color()->equals($base->mix($other->lighten(0.5)->color(), 0.25)->color()), 'the unmapped color of the chain is used');
    }

    public function testScalesNeedAtLeastOneStep(): void
    {
        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('at least one step, 0 given');

        (new HexColor(self::BASE))->tints(0);
    }

    public function testHarmoniesAsChains(): void
    {
        $base = Color::fromHex(self::BASE);
        $chain = new HexColor(self::BASE);

        $triadic = $chain->triadic();
        self::assertCount(3, $triadic);
        self::assertTrue($triadic[0]->color()->isCloseTo($base, Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB)), 'the base is the first member (after the round trip through the wheel)');
        foreach (Harmony::triadic($base, HueWheels::oklch())->members as $i => $member) {
            self::assertTrue($triadic[$i]->color()->equals($member->color), 'member ' . $i);
        }
        $last = $triadic[1]->trace()->steps;
        self::assertSame('harmony', $last[\count($last) - 1]->operation);
        self::assertSame(['scheme' => 'triadic', 'offset' => 120.0, 'wheel' => 'hue-wheel-oklch@r1'], $last[\count($last) - 1]->parameters);

        self::assertCount(3, $chain->analogous(), 'three neighbours by default');
        self::assertCount(5, $chain->analogous(5, 20.0));
        self::assertCount(3, $chain->splitComplementary());

        $hsl = $chain->triadic(HueWheels::hsl());
        foreach (Harmony::triadic($base, HueWheels::hsl())->members as $i => $member) {
            self::assertTrue($hsl[$i]->color()->equals($member->color), 'HSL member ' . $i);
        }
    }

    public function testTypedOutputs(): void
    {
        $rgb = (new HexColor(self::BASE))->toRgb();
        self::assertFloatClose(51.0, $rgb->r, Tolerances::linearAlgebra());
        self::assertFloatClose(102.0, $rgb->g, Tolerances::linearAlgebra());
        self::assertFloatClose(204.0, $rgb->b, Tolerances::linearAlgebra());

        $hsl = (new HexColor(self::BASE))->toHsl();
        self::assertFloatClose(220.0, $hsl->h ?? \NAN, Tolerance::absolute(Tolerances::ANGLE_DEGREES));

        self::assertSame(self::BASE, (string) new HexColor(self::BASE));
    }
}
