<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Interpolation;

use Domm98CZ\Color\Interpolation\MissingComponents;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\ColorValue;
use Domm98CZ\Color\Space\ComponentCategory;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Converter;
use Domm98CZ\Color\Tests\Fake\PercentSrgbSpace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Carrying missing components forward (CSS Color 4 § 13.3).
 */
final class MissingComponentsTest extends TestCase
{
    /**
     * @return iterable<string, array{ColorValue, ColorSpace, list<bool>}>
     */
    public static function carried(): iterable
    {
        yield 'hue lch → oklch' => [new ColorValue(ColorSpaces::lch(), [50.0, 20.0, null]), ColorSpaces::oklch(), [false, false, true]];
        yield 'lightness lab → oklch' => [new ColorValue(ColorSpaces::lab(), [null, 20.0, 30.0]), ColorSpaces::oklch(), [true, false, false]];
        yield 'lightness hsl → lab' => [new ColorValue(ColorSpaces::hsl(), [120.0, 50.0, null]), ColorSpaces::lab(), [true, false, false]];
        yield 'saturation hsl → oklch chroma' => [new ColorValue(ColorSpaces::hsl(), [120.0, null, 50.0]), ColorSpaces::oklch(), [false, true, false]];
        yield 'hue and saturation hsl → lch' => [new ColorValue(ColorSpaces::hsl(), [null, null, 50.0]), ColorSpaces::lch(), [false, true, true]];
        yield 'opponent a lab → oklab' => [new ColorValue(ColorSpaces::lab(), [50.0, null, 30.0]), ColorSpaces::oklab(), [false, true, false]];
        yield 'opponent b oklab → lab' => [new ColorValue(ColorSpaces::oklab(), [0.5, 0.1, null]), ColorSpaces::lab(), [false, false, true]];
        yield 'red srgb → xyz' => [new ColorValue(ColorSpaces::srgb(), [null, 0.5, 0.5]), ColorSpaces::xyzD65(), [true, false, false]];
        yield 'blue display-p3 → rec2020' => [new ColorValue(ColorSpaces::displayP3(), [0.5, 0.5, null]), ColorSpaces::rec2020(), [false, false, true]];
        yield 'set: a and b of lab → c and h of lch' => [new ColorValue(ColorSpaces::lab(), [50.0, null, null]), ColorSpaces::lch(), [false, true, true]];
        yield 'set: a alone is not the whole set' => [new ColorValue(ColorSpaces::oklab(), [0.5, null, 0.1]), ColorSpaces::oklch(), [false, false, false]];
        yield 'set: all of srgb → oklab' => [new ColorValue(ColorSpaces::srgb(), [null, null, null]), ColorSpaces::oklab(), [true, true, true]];
        yield 'set: w and b of hwb → s and l of hsl' => [new ColorValue(ColorSpaces::hwb(), [30.0, null, null]), ColorSpaces::hsl(), [false, true, true]];
        yield 'no analog: blue in oklch' => [new ColorValue(ColorSpaces::displayP3(), [0.7, 0.5, null]), ColorSpaces::oklch(), [false, false, false]];
        yield 'no analog: hwb whiteness in hsl' => [new ColorValue(ColorSpaces::hwb(), [30.0, null, 20.0]), ColorSpaces::hsl(), [false, false, false]];
        yield 'set: hsv value → hsl lightness' => [new ColorValue(ColorSpaces::hsv(), [30.0, 50.0, null]), ColorSpaces::hsl(), [false, false, true]];
        yield 'hsv saturation is colorfulness' => [new ColorValue(ColorSpaces::hsv(), [30.0, null, 50.0]), ColorSpaces::hsl(), [false, true, false]];
        yield 'user space without categories' => [new ColorValue(new PercentSrgbSpace(), [null, 50.0, 50.0]), ColorSpaces::srgb(), [false, false, false]];
        yield 'user space, whole set missing' => [new ColorValue(new PercentSrgbSpace(), [null, null, null]), ColorSpaces::srgb(), [true, true, true]];
    }

    /**
     * @param list<bool> $missing
     */
    #[DataProvider('carried')]
    public function testCarriesForward(ColorValue $color, ColorSpace $target, array $missing): void
    {
        $result = MissingComponents::convert($color, $target);
        $plain = (new Converter())->convert($color->space, $target, $color->coordinates);

        self::assertSame($target, $result->space);
        foreach ($missing as $index => $isMissing) {
            if ($isMissing) {
                self::assertNull($result->coordinates[$index], (string) $index);
            } else {
                self::assertSame($plain[$index], $result->coordinates[$index], (string) $index);
            }
        }
    }

    public function testKeepsTheAlphaAsItIs(): void
    {
        self::assertNull(MissingComponents::convert(new ColorValue(ColorSpaces::srgb(), [1.0, 0.0, 0.0], null), ColorSpaces::oklch())->alpha);
        self::assertSame(0.25, MissingComponents::convert(new ColorValue(ColorSpaces::srgb(), [1.0, 0.0, 0.0], 0.25), ColorSpaces::oklch())->alpha);
    }

    public function testTheSameSpaceIsNotConverted(): void
    {
        // An explicit powerless hue stays as written when no conversion is needed.
        $color = new ColorValue(ColorSpaces::hsl(), [120.0, 0.0, 50.0]);

        self::assertSame($color, MissingComponents::convert($color, ColorSpaces::hsl()));
    }

    public function testAPowerlessHueBecomesMissingAfterConversion(): void
    {
        self::assertNull(MissingComponents::convert(new ColorValue(ColorSpaces::srgb(), [0.5, 0.5, 0.5]), ColorSpaces::oklch())->coordinates[2]);
    }

    public function testEveryBuiltInChannelHasTheCssCategory(): void
    {
        $categories = [];
        foreach (ColorSpaces::all() as $space) {
            $categories[$space->id()] = \implode(',', \array_map(static fn($channel): string => $channel->category->value ?? '-', $space->channels()));
        }

        $rgb = 'reds,greens,blues';
        self::assertSame([
            'xyz-d65' => $rgb, 'xyz-d50' => $rgb, 'srgb' => $rgb, 'srgb-linear' => $rgb, 'display-p3' => $rgb,
            'display-p3-linear' => $rgb, 'a98-rgb' => $rgb, 'a98-rgb-linear' => $rgb, 'prophoto-rgb' => $rgb,
            'prophoto-rgb-linear' => $rgb, 'rec2020' => $rgb, 'rec2020-linear' => $rgb,
            'hsl' => 'hue,colorfulness,lightness', 'hwb' => 'hue,-,-', 'hsv' => 'hue,colorfulness,-',
            'lab' => 'lightness,opponent-a,opponent-b', 'lch' => 'lightness,colorfulness,hue',
            'lab-d65' => 'lightness,opponent-a,opponent-b', 'lch-d65' => 'lightness,colorfulness,hue',
            'oklab' => 'lightness,opponent-a,opponent-b', 'oklch' => 'lightness,colorfulness,hue',
        ], $categories);
        self::assertCount(8, ComponentCategory::cases());
    }
}
