<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Operation;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Interpolation\HueInterpolation;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Notation\CssFormat;
use Domm98CZ\Color\Operation\Exception\InvalidOperationException;
use Domm98CZ\Color\Operation\Exception\UnsupportedOperationSpaceException;
use Domm98CZ\Color\Operation\Gradient;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GradientTest extends TestCase
{
    use FloatAssertions;

    private static function srgbGradient(): Gradient
    {
        return Gradient::of(Color::fromSrgb(1.0, 0.0, 0.0), Color::fromSrgb(0.0, 0.0, 1.0))
            ->withStop(Color::fromSrgb(0.0, 1.0, 0.0), 0.75)
            ->in(ColorSpaces::srgb());
    }

    public function testStopsAreEvenlySpacedAndSorted(): void
    {
        $gradient = Gradient::of(Color::fromHex('#000'), Color::fromHex('#888'), Color::fromHex('#fff'))
            ->withStop(Color::fromHex('#f00'), 0.25)
            ->withStop(Color::fromHex('#0f0'), 0.0)
            ->withStop(Color::fromHex('#00f'), 1.0);

        self::assertSame([0.0, 0.0, 0.25, 0.5, 1.0, 1.0], \array_map(static fn($stop): float => $stop->position, $gradient->stops()));
        self::assertSame('#000000', $gradient->stops()[0]->color->toHex(), 'an existing stop stays before a new one at the same position');
        self::assertSame('#00ff00', $gradient->stops()[1]->color->toHex());
        self::assertSame('#0000ff', $gradient->stops()[5]->color->toHex());
    }

    public function testInterpolatesBetweenTheSurroundingStops(): void
    {
        $gradient = self::srgbGradient();

        self::assertSame([1.0, 0.0, 0.0], $gradient->at(0.0)->coordinates());
        self::assertSame([0.5, 0.5, 0.0], \array_map(static fn(?float $v): float => \round($v ?? \NAN, 12), $gradient->at(0.375)->coordinates()));
        self::assertSame([0.0, 1.0, 0.0], $gradient->at(0.75)->coordinates());
        self::assertSame([0.0, 0.5, 0.5], $gradient->at(0.875)->coordinates());
        self::assertSame([0.0, 0.0, 1.0], $gradient->at(1.0)->coordinates());
    }

    public function testRepeatedEndColorsHoldTheColor(): void
    {
        // of() puts the first and last stops at 0 and 1; repeating their colors at 0.25 and 0.75
        // behaves like CSS stops at 25 % and 75 %.
        $gradient = Gradient::of(Color::fromSrgb(1.0, 0.0, 0.0), Color::fromSrgb(0.0, 0.0, 1.0))
            ->withStop(Color::fromSrgb(1.0, 0.0, 0.0), 0.25)
            ->withStop(Color::fromSrgb(0.0, 0.0, 1.0), 0.75)
            ->in(ColorSpaces::srgb());

        self::assertSame([1.0, 0.0, 0.0], $gradient->at(0.1)->coordinates());
        self::assertSame([0.5, 0.0, 0.5], $gradient->at(0.5)->coordinates());
        self::assertSame([0.0, 0.0, 1.0], $gradient->at(0.9)->coordinates());
    }

    public function testHardEdge(): void
    {
        $gradient = Gradient::of(Color::fromSrgb(1.0, 0.0, 0.0), Color::fromSrgb(0.0, 0.0, 1.0))
            ->withStop(Color::fromSrgb(1.0, 0.0, 0.0), 0.5)
            ->withStop(Color::fromSrgb(0.0, 0.0, 1.0), 0.5)
            ->in(ColorSpaces::srgb());

        self::assertSame([1.0, 0.0, 0.0], $gradient->at(0.49)->coordinates());
        self::assertSame([0.0, 0.0, 1.0], $gradient->at(0.5)->coordinates(), 'the later stop applies at the edge');
        self::assertSame([0.0, 0.0, 1.0], $gradient->at(0.51)->coordinates());
    }

    public function testHardEdgeAtTheStart(): void
    {
        $gradient = Gradient::of(Color::fromSrgb(1.0, 0.0, 0.0), Color::fromSrgb(0.0, 0.0, 1.0))
            ->withStop(Color::fromSrgb(0.0, 1.0, 0.0), 0.0)
            ->in(ColorSpaces::srgb());

        self::assertSame([0.0, 1.0, 0.0], $gradient->at(0.0)->coordinates(), 'the later stop applies at the edge');
        self::assertSame([0.0, 0.5, 0.5], $gradient->at(0.5)->coordinates());
    }

    public function testNamedVariadicColors(): void
    {
        $gradient = Gradient::of(...['from' => Color::fromHex('#000'), 'to' => Color::fromHex('#fff')]);

        self::assertSame([0.0, 1.0], \array_map(static fn($stop): float => $stop->position, $gradient->stops()));
    }

    public function testAllStopsAtOnePosition(): void
    {
        $gradient = Gradient::of(Color::fromSrgb(1.0, 0.0, 0.0), Color::fromSrgb(0.0, 0.0, 1.0))
            ->withStop(Color::fromSrgb(0.0, 1.0, 0.0), 1.0)
            ->in(ColorSpaces::srgb());

        self::assertSame([0.0, 1.0, 0.0], $gradient->at(1.0)->coordinates());
    }

    public function testSample(): void
    {
        $colors = Gradient::of(Color::fromSrgb(0.0, 0.0, 0.0), Color::fromSrgb(1.0, 1.0, 1.0))->in(ColorSpaces::srgb())->sample(5);

        self::assertCount(5, $colors);
        self::assertSame([0.0, 0.25, 0.5, 0.75, 1.0], \array_map(static fn(Color $c): ?float => $c->coordinates()[0], $colors));
        self::assertCount(2, Gradient::of(Color::fromHex('#000'), Color::fromHex('#fff'))->sample(2));
    }

    public function testDefaultSpaceIsOklab(): void
    {
        $gradient = Gradient::of(Color::fromHex('#000'), Color::fromHex('#fff'));

        self::assertSame('oklab', $gradient->method->space->id());
        self::assertSame('oklab', $gradient->at(0.5)->space()->id());
        self::assertFloatClose(0.5, $gradient->at(0.5)->coordinates()[0] ?? \NAN, Tolerances::linearAlgebra());
    }

    public function testToCss(): void
    {
        $gradient = Gradient::of(Color::fromHex('#3366cc'), Color::fromHex('#ffcc00'))
            ->withStop(Color::fromHex('#ff3366'), 0.7);

        self::assertSame('linear-gradient(in oklab, rgb(51, 102, 204) 0%, rgb(255, 51, 102) 70%, rgb(255, 204, 0) 100%)', $gradient->toCss());
        self::assertSame('linear-gradient(in oklch 90deg, rgb(51, 102, 204) 0%, rgb(255, 51, 102) 70%, rgb(255, 204, 0) 100%)', $gradient->in(ColorSpaces::oklch())->toCss(90.0));
        self::assertSame('linear-gradient(in hsl longer hue 45.5deg, rgb(51, 102, 204) 0%, rgb(255, 51, 102) 70%, rgb(255, 204, 0) 100%)', $gradient->in(ColorSpaces::hsl(), HueInterpolation::Longer)->toCss(45.5));
        self::assertSame('linear-gradient(in xyz-d65, rgb(51, 102, 204) 0%, rgb(255, 51, 102) 70%, rgb(255, 204, 0) 100%)', $gradient->in(ColorSpaces::xyzD65(), HueInterpolation::Longer)->toCss());
        self::assertSame('linear-gradient(in srgb 33.3deg, rgb(51, 102, 204) 0%, rgb(255, 51, 102) 70%, rgb(255, 204, 0) 100%)', $gradient->in(ColorSpaces::srgb())->toCss(33.333, new CssFormat(1)));
    }

    public function testToCssParsesBackAsTheSameStops(): void
    {
        $gradient = Gradient::of(Color::fromOklch(0.7, 0.1, 30.0), Color::fromHex('#00f'))->withStop(Color::fromHex('#0f0'), 1 / 3);
        \preg_match_all('/((?:rgb|oklch)\([^)]*\)) ([0-9.]+)%/', $gradient->toCss(), $matches, \PREG_SET_ORDER);

        self::assertCount(3, $matches);
        foreach ($gradient->stops() as $index => $stop) {
            self::assertTrue(Color::parse($matches[$index][1])->isCloseTo($stop->color, Tolerance::absolute(Tolerances::SRGB_EIGHT_BIT_STEP)));
            self::assertFloatClose($stop->position * 100, (float) $matches[$index][2], Tolerance::absolute(Tolerances::CSS_ROUND_TRIP));
        }
    }

    public function testToCssRejectsSpacesCssCannotName(): void
    {
        $this->expectException(UnsupportedOperationSpaceException::class);
        $this->expectExceptionMessage('"hsv"');
        Gradient::of(Color::fromHex('#000'), Color::fromHex('#fff'))->in(ColorSpaces::hsv())->toCss();
    }

    public function testToCssRejectsANonFiniteAngle(): void
    {
        $this->expectException(InvalidOperationException::class);
        Gradient::of(Color::fromHex('#000'), Color::fromHex('#fff'))->toCss(\INF);
    }

    /**
     * @return iterable<string, array{\Closure(): mixed, string}>
     */
    public static function invalid(): iterable
    {
        $black = Color::fromHex('#000');
        $white = Color::fromHex('#fff');

        yield 'one color' => [static fn() => Gradient::of($black), 'at least two colors, 1 given'];
        yield 'no color' => [static fn() => Gradient::of(), 'at least two colors, 0 given'];
        yield 'stop below 0' => [static fn() => Gradient::of($black, $white)->withStop($black, -0.1), 'stop position must be in [0, 1]'];
        yield 'stop above 1' => [static fn() => Gradient::of($black, $white)->withStop($black, 1.1), 'stop position must be in [0, 1]'];
        yield 'position below 0' => [static fn() => Gradient::of($black, $white)->at(-0.1), 'gradient position must be in [0, 1]'];
        yield 'position above 1' => [static fn() => Gradient::of($black, $white)->at(1.1), 'gradient position must be in [0, 1]'];
        yield 'sample of one' => [static fn() => Gradient::of($black, $white)->sample(1), 'at least two colors, 1 requested'];
    }

    /**
     * @param \Closure(): mixed $call
     */
    #[DataProvider('invalid')]
    public function testRejectsInvalidArguments(\Closure $call, string $message): void
    {
        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage($message);
        $call();
    }
}
