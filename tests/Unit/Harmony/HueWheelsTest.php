<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Harmony;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Harmony\ColorOrder;
use Domm98CZ\Color\Harmony\Contract\ClassifyingHueWheel;
use Domm98CZ\Color\Harmony\Exception\InvalidHarmonyException;
use Domm98CZ\Color\Harmony\HueWheels;
use Domm98CZ\Color\Harmony\Wheel\RybHueWheel;
use Domm98CZ\Color\Harmony\Wheel\SpaceHueWheel;
use Domm98CZ\Color\Math\Angle;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HueWheelsTest extends TestCase
{
    use FloatAssertions;

    public function testRybMatchesTheWorkedExampleOfTheSource(): void
    {
        // Gossett and Chen 2004, § 2.1: RYB (1, 0.5, 0.25) → RGB (0.8375, 0.19925, 0.0625)
        $rgb = RybHueWheel::rgb(1.0, 0.5, 0.25);

        foreach ([0.8375, 0.19925, 0.0625] as $index => $value) {
            self::assertFloatClose($value, $rgb[$index], Tolerances::linearAlgebra(), (string) $index);
        }
    }

    /**
     * @return iterable<string, array{float, array{float, float, float}, float}>
     */
    public static function ring(): iterable
    {
        // wheel angle → RGB corner of Figure 3 → its HSL hue
        yield 'red' => [0.0, [1.0, 0.0, 0.0], 0.0];
        yield 'orange' => [60.0, [1.0, 0.5, 0.0], 30.0];
        yield 'yellow' => [120.0, [1.0, 1.0, 0.0], 60.0];
        yield 'green' => [180.0, [0.0, 0.66, 0.2], 120.0 + 60.0 * 0.2 / 0.66];
        yield 'blue' => [240.0, [0.163, 0.373, 0.6], 240.0 + 60.0 * (0.163 - 0.373) / (0.6 - 0.163)];
        yield 'violet' => [300.0, [0.5, 0.0, 0.5], 300.0];
    }

    /**
     * @param array{float, float, float} $rgb
     */
    #[DataProvider('ring')]
    public function testRybRingColorsAndTheirAngles(float $angle, array $rgb, float $hslHue): void
    {
        $wheel = HueWheels::ryb();
        $color = Color::fromSrgb(...$rgb);

        foreach (RybHueWheel::ringColor($angle) as $index => $value) {
            self::assertFloatClose($rgb[$index], $value, Tolerances::linearAlgebra(), (string) $index);
        }
        self::assertFloatClose($hslHue, HueWheels::hsl()->hueOf($color) ?? \NAN, Tolerance::absolute(Tolerances::ANGLE_DEGREES));
        self::assertLessThanOrEqual(Tolerances::ANGLE_DEGREES, Angle::distanceDegrees($angle, $wheel->hueOf($color) ?? \NAN));
        self::assertFloatClose($hslHue, HueWheels::hsl()->hueOf($wheel->colorAt($angle, Color::fromHsl(10.0, 100.0, 50.0))) ?? \NAN, Tolerance::absolute(Tolerances::ANGLE_DEGREES));
    }

    public function testRybMidSectorAngles(): void
    {
        $wheel = HueWheels::ryb();

        // 30° is RYB (1, 0.5, 0): RGB (1, 0.25, 0), HSL hue 15°
        self::assertSame([1.0, 0.25, 0.0], RybHueWheel::ringColor(30.0));
        self::assertEqualsWithDelta(30.0, $wheel->hueOf(Color::fromSrgb(1.0, 0.25, 0.0)) ?? \NAN, Tolerances::ANGLE_DEGREES);
        // 330° is RYB (1, 0, 0.5): RGB (0.75, 0, 0.25), HSL hue 340°
        self::assertSame([0.75, 0.0, 0.25], RybHueWheel::ringColor(330.0));
        self::assertEqualsWithDelta(330.0, $wheel->hueOf(Color::fromSrgb(0.75, 0.0, 0.25)) ?? \NAN, Tolerances::ANGLE_DEGREES);
        self::assertEqualsWithDelta(0.0, $wheel->hueOf(Color::fromSrgb(1.0, 0.0, 0.0)) ?? \NAN, Tolerances::ANGLE_DEGREES);
    }

    public function testRybColorAtKeepsSaturationAndLightness(): void
    {
        $template = Color::fromHsl(200.0, 40.0, 30.0, 0.5);
        $moved = HueWheels::ryb()->colorAt(-240.0, $template); // = 120°, yellow: HSL hue 60°

        self::assertSame('hsl', $moved->space()->id());
        self::assertFloatClose(60.0, $moved->coordinate('h') ?? \NAN, Tolerance::absolute(Tolerances::ANGLE_DEGREES));
        self::assertSame(40.0, $moved->coordinate('s'));
        self::assertSame(30.0, $moved->coordinate('l'));
        self::assertSame(0.5, $moved->alpha());
    }

    public function testSpaceWheelsKeepTheOtherChannels(): void
    {
        $color = Color::fromOklch(0.6, 0.1, 30.0);
        $moved = HueWheels::oklch()->colorAt(390.0, $color);

        self::assertSame([0.6, 0.1, 30.0], $moved->coordinates());
        self::assertSame('#cc9933', HueWheels::hsl()->colorAt(40.0, Color::fromHex('#3366cc'))->toHex());
        self::assertFloatClose(Color::fromHex('#3366cc')->to(ColorSpaces::lch())->coordinate('h') ?? \NAN, HueWheels::cieLch()->hueOf(Color::fromHex('#3366cc')) ?? \NAN, Tolerances::linearAlgebra());
        self::assertTrue(HueWheels::oklch()->colorAt(10.0, Color::of(ColorSpaces::srgb(), [0.2, 0.4, 0.6], null))->isAlphaMissing());
    }

    public function testAchromaticColorsHaveNoHue(): void
    {
        foreach ([HueWheels::oklch(), HueWheels::cieLch(), HueWheels::hsl(), HueWheels::ryb()] as $wheel) {
            self::assertNull($wheel->hueOf(Color::fromHex('#808080')), $wheel->id()->name);
        }
    }

    public function testIdentities(): void
    {
        self::assertSame('hue-wheel-oklch@r1', HueWheels::oklch()->id()->identifier());
        self::assertSame('hue-wheel-cielch[white=D50]@r1', HueWheels::cieLch()->id()->identifier());
        self::assertSame('hue-wheel-hsl@r1', HueWheels::hsl()->id()->identifier());
        self::assertSame('hue-wheel-ryb[interpolation=trilinear]@r1', HueWheels::ryb()->id()->identifier());
        self::assertSame('Paper', HueWheels::ryb()->id()->sources[0]->kind->name);
        self::assertSame('§ 9.2 OkLCh', HueWheels::oklch()->id()->sources[0]->section);
        self::assertSame('§ 9.1 CIE LCH', HueWheels::cieLch()->id()->sources[0]->section);
        self::assertSame('§ 7 HSL', HueWheels::hsl()->id()->sources[0]->section);
    }

    /**
     * @return iterable<string, array{ClassifyingHueWheel, list<string>}>
     */
    public static function positions(): iterable
    {
        yield 'hsl' => [HueWheels::hsl(), ['red', 'red-yellow', 'yellow', 'green-yellow', 'green', 'green-cyan', 'cyan', 'blue-cyan', 'blue', 'blue-magenta', 'magenta', 'red-magenta']];
        yield 'ryb' => [HueWheels::ryb(), ['red', 'red-orange', 'orange', 'yellow-orange', 'yellow', 'yellow-green', 'green', 'blue-green', 'blue', 'blue-violet', 'violet', 'red-violet']];
    }

    /**
     * @param list<string> $names
     */
    #[DataProvider('positions')]
    public function testTwelvePositions(ClassifyingHueWheel $wheel, array $names): void
    {
        $positions = $wheel->positions();

        self::assertSame($names, \array_map(static fn($p): string => $p->name, $positions));
        foreach ($positions as $index => $position) {
            self::assertSame($index * 30.0, $position->angle);
            self::assertSame($index % 4 === 0 ? ColorOrder::Primary : ($index % 2 === 0 ? ColorOrder::Secondary : ColorOrder::Tertiary), $position->order);
        }
    }

    /**
     * @return iterable<string, array{ClassifyingHueWheel, string, ColorOrder, ?string}>
     */
    public static function classifications(): iterable
    {
        yield 'hsl red' => [HueWheels::hsl(), '#ff0000', ColorOrder::Primary, 'red'];
        yield 'hsl cyan' => [HueWheels::hsl(), '#00ffff', ColorOrder::Secondary, 'cyan'];
        yield 'hsl orange is red-yellow' => [HueWheels::hsl(), '#ff8000', ColorOrder::Tertiary, 'red-yellow'];
        yield 'hsl 350° is red' => [HueWheels::hsl(), 'hsl(350 100% 50%)', ColorOrder::Primary, 'red'];
        yield 'hsl exactly 15° takes the later position' => [HueWheels::hsl(), 'hsl(15 100% 50%)', ColorOrder::Tertiary, 'red-yellow'];
        yield 'hsl just below 15°' => [HueWheels::hsl(), 'hsl(14.9 100% 50%)', ColorOrder::Primary, 'red'];
        yield 'ryb orange' => [HueWheels::ryb(), '#ff8000', ColorOrder::Secondary, 'orange'];
        yield 'ryb green corner' => [HueWheels::ryb(), 'rgb(0% 66% 20%)', ColorOrder::Secondary, 'green'];
        yield 'ryb violet corner' => [HueWheels::ryb(), 'rgb(50% 0% 50%)', ColorOrder::Secondary, 'violet'];
        yield 'ryb yellow' => [HueWheels::ryb(), '#ffff00', ColorOrder::Primary, 'yellow'];
        yield 'ryb gray' => [HueWheels::ryb(), '#777777', ColorOrder::Achromatic, null];
    }

    #[DataProvider('classifications')]
    public function testClassify(ClassifyingHueWheel $wheel, string $css, ColorOrder $order, ?string $name): void
    {
        $classification = $wheel->classify(Color::parse($css));

        self::assertSame($order, $classification->order);
        self::assertSame($name, $classification->position?->name);
        self::assertSame($wheel->id()->identifier(), $classification->wheel->identifier());

        if ($name === null) {
            self::assertNull($classification->hue);
            self::assertNull($classification->distance);
        } else {
            self::assertNotNull($classification->distance);
            self::assertLessThanOrEqual(15.0, $classification->distance);
            self::assertNotNull($classification->hue);
        }
    }

    public function testClassificationDistance(): void
    {
        $classification = HueWheels::hsl()->classify(Color::parse('hsl(10 100% 50%)'));

        self::assertFloatClose(10.0, $classification->distance ?? \NAN, Tolerance::absolute(Tolerances::ANGLE_DEGREES));
        self::assertFloatClose(10.0, $classification->hue ?? \NAN, Tolerance::absolute(Tolerances::ANGLE_DEGREES));
    }

    public function testInvalidWheels(): void
    {
        try {
            new SpaceHueWheel(ColorSpaces::oklab(), new AlgorithmId('x'));
            self::fail('oklab has no hue');
        } catch (InvalidHarmonyException $exception) {
            self::assertStringContainsString('"oklab" has no hue channel', $exception->getMessage());
        }

        foreach ([HueWheels::oklch(), HueWheels::ryb()] as $wheel) {
            try {
                $wheel->colorAt(\NAN, Color::fromHex('#3366cc'));
                self::fail('NaN angle');
            } catch (InvalidHarmonyException $exception) {
                self::assertStringContainsString('must be finite', $exception->getMessage());
            }
        }
    }
}
