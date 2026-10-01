<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Operation;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Interpolation\Exception\InvalidInterpolationException;
use Domm98CZ\Color\Interpolation\HueInterpolation;
use Domm98CZ\Color\Operation\CssColorMix;
use Domm98CZ\Color\Operation\Exception\InvalidOperationException;
use Domm98CZ\Color\Operation\Mixer;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MixerTest extends TestCase
{
    use FloatAssertions;

    public function testDefaultsToOklab(): void
    {
        $mixer = new Mixer();

        self::assertSame('oklab', $mixer->method->space->id());
        self::assertSame(HueInterpolation::Shorter, $mixer->method->hue);
        self::assertSame('oklab', $mixer->mix(Color::fromHex('#fff'), Color::fromHex('#000'))->space()->id());
    }

    public function testWeightIsTheAmountOfTheSecondColor(): void
    {
        $mixer = new Mixer(ColorSpaces::srgb());
        $red = Color::fromSrgb(1.0, 0.0, 0.0);
        $blue = Color::fromSrgb(0.0, 0.0, 1.0);

        self::assertSame([0.75, 0.0, 0.25], $mixer->mix($red, $blue, 0.25)->coordinates());
        self::assertSame([1.0, 0.0, 0.0], $mixer->mix($red, $blue, 0.0)->coordinates());
        self::assertSame([0.0, 0.0, 1.0], $mixer->mix($red, $blue, 1.0)->coordinates());
        self::assertSame([0.5, 0.0, 0.5], $mixer->mix($red, $blue)->coordinates());
    }

    public function testSassLikeSrgbMixIsDarkerThanLinearLight(): void
    {
        $white = Color::fromHex('#fff');
        $black = Color::fromHex('#000');

        $gamma = (new Mixer(ColorSpaces::srgb()))->mix($white, $black)->coordinates()[0];
        $linear = (new Mixer(ColorSpaces::srgbLinear()))->mix($white, $black)->to(ColorSpaces::srgb())->coordinates()[0];

        self::assertSame(0.5, $gamma);
        self::assertNotNull($linear);
        self::assertGreaterThan(0.73, $linear);
    }

    public function testHueMethod(): void
    {
        $a = Color::fromOklch(0.6, 0.1, 30.0);
        $b = Color::fromOklch(0.6, 0.1, 90.0);

        self::assertSame(60.0, (new Mixer(ColorSpaces::oklch()))->mix($a, $b)->coordinate('h'));
        self::assertSame(240.0, (new Mixer(ColorSpaces::oklch(), HueInterpolation::Longer))->mix($a, $b)->coordinate('h'));
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function invalidWeights(): iterable
    {
        yield 'negative' => [-0.5];
        yield 'above one' => [1.5];
        yield 'NaN' => [\NAN];
    }

    #[DataProvider('invalidWeights')]
    public function testRejectsInvalidWeights(float $weight): void
    {
        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('The mix weight must be in [0, 1]');
        (new Mixer())->mix(Color::fromHex('#fff'), Color::fromHex('#000'), $weight);
    }

    public function testMixManyIsAWeightedAverage(): void
    {
        $mixer = new Mixer(ColorSpaces::srgb());
        $result = $mixer->mixMany([
            [Color::fromSrgb(0.9, 0.0, 0.0), 1.0],
            [Color::fromSrgb(0.0, 0.9, 0.0), 2.0],
            [Color::fromSrgb(0.0, 0.0, 0.9), 0.0],
        ]);

        foreach ([0.3, 0.6, 0.0] as $index => $value) {
            self::assertFloatClose($value, $result->coordinates()[$index] ?? \NAN, Tolerances::linearAlgebra(), (string) $index);
        }
    }

    public function testMixManyWithOneColorConvertsIt(): void
    {
        $result = (new Mixer())->mixMany([[Color::fromHex('#fff'), 3.0]]);

        self::assertSame('oklab', $result->space()->id());
        self::assertFloatClose(1.0, $result->coordinates()[0] ?? \NAN, Tolerances::linearAlgebra());
    }

    public function testMixManyWithLeadingZeroWeights(): void
    {
        $result = (new Mixer(ColorSpaces::srgb()))->mixMany([
            [Color::fromSrgb(1.0, 0.0, 0.0), 0.0],
            [Color::fromSrgb(0.0, 1.0, 0.0), 0.0],
            [Color::fromSrgb(0.0, 0.0, 1.0), 1.0],
        ]);

        self::assertSame([0.0, 0.0, 1.0], $result->coordinates());
    }

    /**
     * @return iterable<string, array{list<array{Color, float}>, string}>
     */
    public static function invalidMixMany(): iterable
    {
        $white = Color::fromHex('#fff');

        yield 'empty' => [[], 'at least one color'];
        yield 'negative weight' => [[[$white, 1.0], [$white, -1.0]], 'finite numbers ≥ 0'];
        yield 'NaN weight' => [[[$white, \NAN]], 'finite numbers ≥ 0'];
        yield 'infinite weight' => [[[$white, \INF]], 'finite numbers ≥ 0'];
        yield 'all zero' => [[[$white, 0.0], [$white, 0.0]], 'greater than 0'];
    }

    /**
     * @param list<array{Color, float}> $items
     */
    #[DataProvider('invalidMixMany')]
    public function testMixManyRejects(array $items, string $message): void
    {
        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage($message);
        (new Mixer())->mixMany($items); // @phpstan-ignore argument.type (the empty list is the invalid input)
    }

    public function testCssColorMixMatchesTheParser(): void
    {
        $a = Color::fromHex('#3366cc');
        $b = Color::fromHex('#ffcc00');

        $mixed = CssColorMix::mix(ColorSpaces::oklch(), HueInterpolation::Longer, [$a, 0.3], [$b, null]);
        $parsed = Color::parse('color-mix(in oklch longer hue, #3366cc 30%, #ffcc00)');

        self::assertTrue($mixed->equals($parsed));
    }

    public function testCssColorMixAlphaMultiplier(): void
    {
        $mixed = CssColorMix::mix(ColorSpaces::srgb(), HueInterpolation::Shorter, [Color::fromHex('#f00'), 0.2], [Color::fromHex('#00f'), 0.2]);

        self::assertSame([0.5, 0.0, 0.5], $mixed->coordinates());
        self::assertFloatClose(0.4, $mixed->alpha(), Tolerances::linearAlgebra());
    }

    public function testCssColorMixRejectsInvalidPercentages(): void
    {
        $this->expectException(InvalidInterpolationException::class);
        CssColorMix::mix(ColorSpaces::srgb(), HueInterpolation::Shorter, [Color::fromHex('#f00'), 1.2]);
    }
}
