<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Palette;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Contrast\Contrast;
use Domm98CZ\Color\Difference\DeltaE;
use Domm98CZ\Color\Gamut\GamutMappers;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Palette\Exception\InvalidPaletteException;
use Domm98CZ\Color\Palette\Palette;
use Domm98CZ\Color\Palette\PaletteAnalyzer;
use Domm98CZ\Color\Palette\Scales;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Fake\AsymmetricDeltaE;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScalesAndAnalyzerTest extends TestCase
{
    use FloatAssertions;

    public function testTintsShadesTones(): void
    {
        $base = Color::fromSrgb(0.2, 0.4, 0.6);

        $tints = Scales::tints(ColorSpaces::srgb(), 4)->generate($base)->palette->colors();
        self::assertCount(4, $tints);
        self::assertSame([0.2, 0.4, 0.6], $tints[0]->coordinates());
        self::assertSame([0.4, 0.55, 0.7], self::rounded($tints[1]));
        self::assertSame([0.8, 0.85, 0.9], self::rounded($tints[3]), 'amounts k/4, white not included');

        $shades = Scales::shades(ColorSpaces::srgb(), 2)->generate($base)->palette->colors();
        self::assertSame([0.1, 0.2, 0.3], self::rounded($shades[1]));

        $tones = Scales::tones(ColorSpaces::srgb(), 2, Color::fromSrgb(0.5, 0.5, 0.5))->generate($base)->palette->colors();
        self::assertSame([0.35, 0.45, 0.55], self::rounded($tones[1]));

        $defaultTone = Scales::tones(ColorSpaces::oklab(), 3)->generate($base)->palette->colors();
        self::assertFloatClose($base->to(ColorSpaces::oklab())->coordinate('l') ?? \NAN, $defaultTone[2]->coordinate('l') ?? \NAN, Tolerances::linearAlgebra());
    }

    public function testLightnessStepsWithAndWithoutMapping(): void
    {
        $base = Color::fromHex('#3366cc');
        $lightness = [0.97, 0.6, 0.2];

        $raw = Scales::lightnessSteps(ColorSpaces::oklch(), $lightness)->generate($base);
        self::assertSame([0.97, 0.6, 0.2], \array_map(static fn(Color $c): ?float => $c->coordinate('l'), $raw->palette->colors()));
        self::assertSame([null, null, null], $raw->mapping);
        self::assertSame([], $raw->mappedSteps());
        self::assertSame($base->to(ColorSpaces::oklch())->coordinate('c'), $raw->palette->get(1)->coordinate('c'));

        $mapped = Scales::lightnessSteps(ColorSpaces::oklch(), $lightness)->generate($base, GamutMappers::cssColor4());
        self::assertSame([0, 2], $mapped->mappedSteps());
        foreach ($mapped->palette->colors() as $color) {
            self::assertSame('srgb', $color->space()->id());
            self::assertTrue($color->isInGamut());
        }

        $p3 = Scales::lightnessSteps(ColorSpaces::lab(), [50.0])->generate($base, GamutMappers::clip(), ColorSpaces::displayP3());
        self::assertSame('display-p3', $p3->palette->get(0)->space()->id());
        self::assertSame(50.0, Scales::lightnessSteps(ColorSpaces::hsl(), [50.0])->generate($base)->palette->get(0)->coordinate('l'));
        self::assertTrue(Scales::lightnessSteps(ColorSpaces::oklch(), [0.5])->generate(Color::of(ColorSpaces::srgb(), [0.2, 0.4, 0.6], null))->palette->get(0)->isAlphaMissing());
    }

    /**
     * @return iterable<string, array{\Closure(): mixed, string}>
     */
    public static function invalid(): iterable
    {
        yield 'one tint' => [static fn() => Scales::tints(ColorSpaces::oklab(), 1), 'at least 2 steps, 1 given'];
        yield 'no lightness channel' => [static fn() => Scales::lightnessSteps(ColorSpaces::srgb(), [0.5]), '"srgb" has no lightness channel'];
        yield 'hsv value is not a lightness' => [static fn() => Scales::lightnessSteps(ColorSpaces::hsv(), [50.0]), '"hsv" has no lightness channel'];
        yield 'NaN lightness' => [static fn() => Scales::lightnessSteps(ColorSpaces::oklch(), [0.5, \NAN]), 'must be finite'];
    }

    /**
     * @param \Closure(): mixed $call
     */
    #[DataProvider('invalid')]
    public function testInvalid(\Closure $call, string $message): void
    {
        $this->expectException(InvalidPaletteException::class);
        $this->expectExceptionMessage($message);
        $call();
    }

    public function testAnalyzer(): void
    {
        $palette = Palette::of(Color::fromHex('#3366cc'), Color::fromHex('#3a6bd0'), Color::fromHex('#ffffff'), Color::fromHex('#000000'));
        $analysis = (new PaletteAnalyzer(DeltaE::ok()))->analyze($palette);

        self::assertSame('deltae-ok', $analysis->metric->name);
        self::assertSame($palette, $analysis->palette);
        self::assertCount(4, $analysis->differences);
        self::assertSame(0.0, $analysis->differences[2][2]);
        self::assertSame($analysis->differences[0][1], $analysis->differences[1][0], 'ΔEOK is symmetric');
        self::assertNotNull($analysis->minDifference);
        self::assertSame([0, 1], [$analysis->minDifference->first, $analysis->minDifference->second]);
        self::assertNotNull($analysis->maxDifference);
        self::assertSame([2, 3], [$analysis->maxDifference->first, $analysis->maxDifference->second]);
        self::assertFloatClose(1.0, $analysis->maxDifference->difference->value, Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB));
        self::assertSame([[0, 1], [1, 0], [2, 1], [3, 0]], \array_map(static fn($p): array => [$p->first, $p->second], $analysis->nearest));
    }

    public function testAsymmetricMetricAndTies(): void
    {
        $palette = Palette::of(Color::fromHex('#3366cc'), Color::fromHex('#cc6633'), Color::fromHex('#3366cc'));
        $analysis = (new PaletteAnalyzer(new AsymmetricDeltaE()))->analyze($palette);

        self::assertNotSame($analysis->differences[0][1], $analysis->differences[1][0], 'the fake weights by the reference hue');
        self::assertNotNull($analysis->minDifference);
        self::assertSame([0, 2], [$analysis->minDifference->first, $analysis->minDifference->second]);
        self::assertSame(0.0, $analysis->minDifference->difference->value);
        self::assertNotNull($analysis->maxDifference);
        self::assertSame([0, 1], [$analysis->maxDifference->first, $analysis->maxDifference->second], 'the first of equal pairs');
        self::assertSame([[0, 2], [1, 0], [2, 0]], \array_map(static fn($p): array => [$p->first, $p->second], $analysis->nearest));
    }

    public function testTiesWithASymmetricMetric(): void
    {
        $a = Color::fromHex('#3366cc');
        $b = Color::fromHex('#cc6633');

        $analysis = (new PaletteAnalyzer(DeltaE::ok()))->analyze(Palette::of($a, $b, $a));
        self::assertNotNull($analysis->maxDifference);
        self::assertSame([0, 1], [$analysis->maxDifference->first, $analysis->maxDifference->second], 'the first of equal maxima');

        $same = (new PaletteAnalyzer(DeltaE::ok()))->analyze(Palette::of($a, $a, $a));
        self::assertNotNull($same->minDifference);
        self::assertSame([0, 1], [$same->minDifference->first, $same->minDifference->second], 'the first of equal minima');
        self::assertSame([[0, 1], [1, 0], [2, 0]], \array_map(static fn($p): array => [$p->first, $p->second], $same->nearest));
    }

    public function testSingleColor(): void
    {
        $analysis = (new PaletteAnalyzer(DeltaE::ok()))->analyze(Palette::of(Color::fromHex('#fff')));

        self::assertNull($analysis->minDifference);
        self::assertNull($analysis->maxDifference);
        self::assertSame([], $analysis->nearest);
        self::assertSame([[0.0]], $analysis->differences);
    }

    public function testContrastMatrix(): void
    {
        $analysis = (new PaletteAnalyzer(DeltaE::ok()))->analyze(Palette::of(Color::fromHex('#777777'), Color::fromHex('#ffffff')));
        $matrix = $analysis->contrastMatrix(Contrast::wcag2());

        self::assertSame(1.0, $matrix[0][0]);
        self::assertEqualsWithDelta(4.478089, $matrix[0][1], Tolerances::printedDecimals(6));
        self::assertSame($matrix[0][1], $matrix[1][0]);
    }

    /**
     * @return list<float>
     */
    private static function rounded(Color $color): array
    {
        return \array_map(static fn(?float $v): float => \round($v ?? \NAN, 12), $color->coordinates());
    }
}
