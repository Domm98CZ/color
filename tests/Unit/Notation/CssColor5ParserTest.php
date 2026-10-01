<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Notation;

use Domm98CZ\Color\Notation\CssParser;
use Domm98CZ\Color\Notation\Exception\ColorParseException;
use Domm98CZ\Color\Notation\Exception\UnresolvableCssColorException;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * color-mix() and relative color syntax in the parser (CSS Color 5). The bulk of the behaviour is
 * checked against web-platform-tests and the specification examples; these cases pin the library
 * decisions and the boundaries.
 */
final class CssColor5ParserTest extends TestCase
{
    use FloatAssertions;

    /**
     * @return iterable<string, array{string, string, list<float|null>, float|null}>
     */
    public static function colors(): iterable
    {
        // Relative colors
        yield 'keywords give the origin' => ['rgb(from rgb(51 102 153) r g b)', 'srgb', [0.2, 0.4, 0.6], 1.0];
        yield 'keyword in another position keeps its number' => ['rgb(from rgb(51 102 153) b r g)', 'srgb', [0.6, 0.2, 0.4], 1.0];
        yield 'hsl keyword used in lab keeps the number' => ['lab(from hsl(120 50% 40%) 40 0 0)', 'lab', [40.0, 0.0, 0.0], 1.0];
        yield 'relative rgb is not clamped' => ['rgb(from red 300 -10 b)', 'srgb', [300 / 255, -10 / 255, 0.0], 1.0];
        yield 'relative color() is not clamped' => ['color(from red srgb 2 -1 150%)', 'srgb', [2.0, -1.0, 1.5], 1.0];
        yield 'relative hsl saturation is not clamped' => ['hsl(from red h -10 l)', 'hsl', [0.0, -10.0, 50.0], 1.0];
        yield 'relative lab lightness is clamped' => ['lab(from red 120 a b)', 'lab', [100.0, null, null], 1.0];
        yield 'relative lch chroma is clamped at 0' => ['lch(from red l -5 h)', 'lch', [null, 0.0, null], 1.0];
        yield 'relative oklab lightness is clamped' => ['oklab(from red 1.5 a b)', 'oklab', [1.0, null, null], 1.0];
        yield 'keyword in a clamped position is clamped' => ['lab(from lab(50 120 0) a a a)', 'lab', [100.0, 120.0, 120.0], 1.0];
        yield 'omitted alpha is the origin alpha' => ['rgb(from rgb(0 0 0 / 0.25) r g b)', 'srgb', [0.0, 0.0, 0.0], 0.25];
        yield 'missing origin alpha stays missing' => ['rgb(from rgb(0 0 0 / none) r g b)', 'srgb', [0.0, 0.0, 0.0], null];
        yield 'alpha keyword' => ['rgb(from rgb(0 0 0 / 0.25) r g b / alpha)', 'srgb', [0.0, 0.0, 0.0], 0.25];
        yield 'channel keyword as alpha is clamped' => ['color(from color(srgb 0.2 3 -1) srgb r g b / g)', 'srgb', [0.2, 3.0, -1.0], 1.0];
        yield 'channel keyword as alpha, negative' => ['color(from color(srgb 0.2 3 -1) srgb r g b / b)', 'srgb', [0.2, 3.0, -1.0], 0.0];
        yield 'missing keyword as alpha' => ['color(from color(srgb none 0 0) srgb r g b / r)', 'srgb', [null, 0.0, 0.0], null];
        yield 'none' => ['rgb(from red none g none / none)', 'srgb', [null, 0.0, null], null];
        yield 'hue keyword in [0, 360)' => ['hsl(from hsl(-30 50% 50%) h s l)', 'hsl', [330.0, 50.0, 50.0], 1.0];
        yield 'number as hue, kept as written' => ['lch(from red l c 400)', 'lch', [null, null, 400.0], 1.0];
        yield 'angle as hue' => ['oklch(from red l c 0.5turn)', 'oklch', [null, null, 180.0], 1.0];
        yield 'xyz keywords' => ['color(from color(xyz 0.1 0.2 0.3) xyz z y x)', 'xyz-d65', [0.3, 0.2, 0.1], 1.0];
        yield 'rgba() with from' => ['rgba(from red r g b / 0.5)', 'srgb', [1.0, 0.0, 0.0], 0.5];
        yield 'hsla() with from' => ['hsla(from red h s l)', 'hsl', [0.0, 100.0, 50.0], 1.0];
        yield 'nested origin' => ['rgb(from rgb(from red r 255 b) r g b)', 'srgb', [1.0, 1.0, 0.0], 1.0];
        yield 'color-mix() as origin' => ['rgb(from color-mix(in srgb, red, blue) r g b)', 'srgb', [0.5, 0.0, 0.5], 1.0];
        // alpha()
        yield 'alpha() replaces the alpha' => ['alpha(from oklch(0.5 0.1 30 / 0.3) / 0.8)', 'oklch', [0.5, 0.1, 30.0], 0.8];
        yield 'alpha() without a value keeps the color' => ['alpha(from oklch(0.5 0.1 30 / 0.3))', 'oklch', [0.5, 0.1, 30.0], 0.3];
        yield 'alpha() with the alpha keyword' => ['alpha(from oklch(0.5 0.1 30 / 0.3) / alpha)', 'oklch', [0.5, 0.1, 30.0], 0.3];
        yield 'alpha() with none' => ['alpha(from red / none)', 'srgb', [1.0, 0.0, 0.0], null];
        yield 'alpha() percentage' => ['ALPHA(FROM red / 20%)', 'srgb', [1.0, 0.0, 0.0], 0.2];
        // color-mix()
        yield 'default space is oklab' => ['color-mix(white, black)', 'oklab', [0.5, 0.0, 0.0], 1.0];
        yield 'percentage before the color' => ['color-mix(in srgb, 25% red, blue)', 'srgb', [0.25, 0.0, 0.75], 1.0];
        yield 'percentage after the color' => ['color-mix(in srgb, red 25%, blue)', 'srgb', [0.25, 0.0, 0.75], 1.0];
        yield 'bounds 0 % and 100 %' => ['color-mix(in srgb, red 0%, blue 100%)', 'srgb', [0.0, 0.0, 1.0], 1.0];
        yield 'nested color-mix()' => ['color-mix(in srgb, color-mix(in srgb, red, blue), lime)', 'srgb', [0.25, 0.5, 0.25], 1.0];
        yield 'hue method' => ['color-mix(in oklch longer hue, oklch(0.5 0.1 30), oklch(0.5 0.1 90))', 'oklch', [0.5, 0.1, 240.0], 1.0];
        yield 'xyz-d65 by name' => ['color-mix(in xyz-d65, color(xyz 0 0 0), color(xyz 1 1 1))', 'xyz-d65', [0.5, 0.5, 0.5], 1.0];
        yield 'xyz is xyz-d65' => ['color-mix(in xyz, color(xyz 0 0 0), color(xyz 1 1 1))', 'xyz-d65', [0.5, 0.5, 0.5], 1.0];
        yield 'case-insensitive' => ['COLOR-MIX(IN SRGB, RED, BLUE)', 'srgb', [0.5, 0.0, 0.5], 1.0];
        yield 'relative color inside color-mix()' => ['color-mix(in srgb, rgb(from red r 0 b), blue)', 'srgb', [0.5, 0.0, 0.5], 1.0];
    }

    /**
     * @param list<float|null> $coordinates null = missing or not checked
     */
    #[DataProvider('colors')]
    public function testParses(string $css, string $space, array $coordinates, ?float $alpha): void
    {
        $value = (new CssParser())->parse($css);

        self::assertSame($space, $value->space->id(), $css);
        foreach ($coordinates as $index => $expected) {
            if ($expected === null) {
                continue;
            }

            self::assertNotNull($value->coordinates[$index], $css . ' #' . $index);
            self::assertFloatClose($expected, $value->coordinates[$index], Tolerances::linearAlgebra(), $css . ' #' . $index);
        }

        if ($alpha === null) {
            self::assertNull($value->alpha, $css);
        } else {
            self::assertNotNull($value->alpha, $css);
            self::assertFloatClose($alpha, $value->alpha, Tolerances::linearAlgebra(), $css);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function expectations(): iterable
    {
        yield 'absolute component' => ['lab(1 light 3)', 'a number, a percentage or "none"'];
        yield 'relative component' => ['lab(from red l light b)', 'a number, a percentage, a channel keyword or "none"'];
        yield 'hue method in a polar space' => ['color-mix(in oklch sideways hue, red, blue)', '"shorter hue", "longer hue", "increasing hue", "decreasing hue" or ","'];
        yield 'hue method in a rectangular space' => ['color-mix(in lab longer hue, red, blue)', '","'];
    }

    #[DataProvider('expectations')]
    public function testErrorsSayWhatWasExpected(string $css, string $expected): void
    {
        try {
            (new CssParser())->parse($css);
            self::fail($css);
        } catch (ColorParseException $exception) {
            self::assertSame($expected, $exception->getExpected());
        }
    }

    public function testMissingCoordinatesThatMustStayMissing(): void
    {
        $parser = new CssParser();

        self::assertSame([null, 0.0, null], $parser->parse('rgb(from red none g none)')->coordinates);
        self::assertNull($parser->parse('lch(from white l c h)')->coordinates[2], 'powerless hue of the converted origin is missing');
        self::assertNull($parser->parse('oklch(from lch(50% 20 none) l c h)')->coordinates[2], 'carried forward');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unresolvable(): iterable
    {
        yield 'currentcolor as origin' => ['rgb(from currentcolor r g b)'];
        yield 'system color in color-mix()' => ['color-mix(in srgb, canvas, red)'];
        yield 'light-dark() in color-mix()' => ['color-mix(in srgb, light-dark(red, blue), red)'];
    }

    #[DataProvider('unresolvable')]
    public function testDocumentDependentColorsInsideCss5Functions(string $css): void
    {
        $this->expectException(UnresolvableCssColorException::class);
        (new CssParser())->parse($css);
    }
}
