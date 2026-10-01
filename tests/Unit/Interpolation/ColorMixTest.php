<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Interpolation;

use Domm98CZ\Color\Interpolation\ColorMix;
use Domm98CZ\Color\Interpolation\Exception\InvalidInterpolationException;
use Domm98CZ\Color\Interpolation\InterpolationMethod;
use Domm98CZ\Color\Interpolation\MixItem;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\ColorValue;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * color-mix() percentage normalisation (CSS Values 5 § 6.1) and the mixing loop (CSS Color 5 § 3.3).
 */
final class ColorMixTest extends TestCase
{
    use FloatAssertions;

    /**
     * @return iterable<string, array{list<float|null>, list<float>, float}>
     */
    public static function normalizations(): iterable
    {
        yield 'both omitted' => [[null, null], [0.5, 0.5], 0.0];
        yield 'one given' => [[0.3, null], [0.3, 0.7], 0.0];
        yield 'both given, sum 100 %' => [[0.25, 0.75], [0.25, 0.75], 0.0];
        yield 'sum above 100 % is scaled down' => [[0.8, 0.8], [0.5, 0.5], 0.0];
        yield 'sum below 100 % is scaled up with a leftover' => [[0.2, 0.6], [0.25, 0.75], 0.2];
        yield 'all zero' => [[0.0, 0.0], [0.0, 0.0], 1.0];
        yield 'specified sum above 100 % leaves nothing for omitted items' => [[0.7, 0.6, null], [0.7 / 1.3, 0.6 / 1.3, 0.0], 0.0];
        yield 'omitted items share the rest' => [[0.4, null, null], [0.4, 0.3, 0.3], 0.0];
        yield 'single item below 100 %' => [[0.5], [1.0], 0.5];
        yield 'single item omitted' => [[null], [1.0], 0.0];
    }

    /**
     * @param list<float|null> $percentages
     * @param list<float> $expected
     */
    #[DataProvider('normalizations')]
    public function testNormalize(array $percentages, array $expected, float $leftover): void
    {
        $color = new ColorValue(ColorSpaces::srgb(), [0.0, 0.0, 0.0]);
        $items = \array_map(static fn(?float $p): MixItem => new MixItem($color, $p), $percentages);
        \assert($items !== []);

        [$normalized, $actualLeftover] = ColorMix::normalize($items);

        self::assertCount(\count($expected), $normalized);
        foreach ($expected as $index => $value) {
            self::assertFloatClose($value, $normalized[$index], Tolerances::linearAlgebra(), (string) $index);
        }
        self::assertFloatClose($leftover, $actualLeftover, Tolerances::linearAlgebra(), 'leftover');
    }

    public function testTheAlphaMultiplier(): void
    {
        $red = new ColorValue(ColorSpaces::srgb(), [1.0, 0.0, 0.0]);
        $result = ColorMix::mix(new InterpolationMethod(ColorSpaces::srgb()), [new MixItem($red, 0.3)]);

        self::assertSame([1.0, 0.0, 0.0], $result->coordinates);
        self::assertNotNull($result->alpha);
        self::assertFloatClose(0.3, $result->alpha, Tolerances::linearAlgebra());
    }

    public function testAMissingAlphaStaysMissingUnderTheMultiplier(): void
    {
        $red = new ColorValue(ColorSpaces::srgb(), [1.0, 0.0, 0.0], null);

        self::assertNull(ColorMix::mix(new InterpolationMethod(ColorSpaces::srgb()), [new MixItem($red, 0.3)])->alpha);
    }

    public function testASingleItemIsConvertedToTheInterpolationSpace(): void
    {
        $gray = new ColorValue(ColorSpaces::srgb(), [0.5, 0.5, 0.5]);
        $result = ColorMix::mix(new InterpolationMethod(ColorSpaces::oklch()), [new MixItem($gray)]);

        self::assertSame('oklch', $result->space->id());
        self::assertNull($result->coordinates[2]);
        self::assertSame(1.0, $result->alpha);
    }

    public function testThreeItemsAreAWeightedAverageInARectangularSpace(): void
    {
        $method = new InterpolationMethod(ColorSpaces::srgb());
        $items = [
            new MixItem(new ColorValue(ColorSpaces::srgb(), [0.9, 0.0, 0.0]), 0.5),
            new MixItem(new ColorValue(ColorSpaces::srgb(), [0.0, 0.6, 0.0]), 0.3),
            new MixItem(new ColorValue(ColorSpaces::srgb(), [0.0, 0.0, 0.3]), 0.2),
        ];
        $result = ColorMix::mix($method, $items);

        foreach ([0.45, 0.18, 0.06] as $index => $value) {
            self::assertNotNull($result->coordinates[$index]);
            self::assertFloatClose($value, $result->coordinates[$index], Tolerances::linearAlgebra(), (string) $index);
        }
    }

    public function testZeroPercentagesMixHalfAndHalfWithZeroAlpha(): void
    {
        $method = new InterpolationMethod(ColorSpaces::srgb());
        $result = ColorMix::mix($method, [
            new MixItem(new ColorValue(ColorSpaces::srgb(), [1.0, 0.0, 0.0]), 0.0),
            new MixItem(new ColorValue(ColorSpaces::srgb(), [0.0, 0.0, 1.0]), 0.0),
        ]);

        self::assertSame([0.5, 0.0, 0.5], $result->coordinates);
        self::assertSame(0.0, $result->alpha);
    }

    public function testRejectsAnEmptyList(): void
    {
        $this->expectException(InvalidInterpolationException::class);
        ColorMix::mix(InterpolationMethod::oklab(), []);
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function invalidPercentages(): iterable
    {
        yield 'negative' => [-0.1];
        yield 'above 100 %' => [1.01];
        yield 'NaN' => [\NAN];
    }

    #[DataProvider('invalidPercentages')]
    public function testRejectsInvalidPercentages(float $percentage): void
    {
        $this->expectException(InvalidInterpolationException::class);
        $this->expectExceptionMessage('A mix percentage must be in [0, 1]');
        new MixItem(new ColorValue(ColorSpaces::srgb(), [0.0, 0.0, 0.0]), $percentage);
    }

    public function testAcceptsTheBounds(): void
    {
        $black = new ColorValue(ColorSpaces::srgb(), [0.0, 0.0, 0.0]);

        self::assertSame(0.0, (new MixItem($black, 0.0))->percentage);
        self::assertSame(1.0, (new MixItem($black, 1.0))->percentage);
    }
}
