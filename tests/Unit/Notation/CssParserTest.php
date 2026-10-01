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

final class CssParserTest extends TestCase
{
    use FloatAssertions;

    /**
     * @return iterable<string, array{string, string, list<float|null>, float|null}>
     */
    public static function valid(): iterable
    {
        // hex and keywords
        yield 'hex 3' => ['#f80', 'srgb', [1.0, 136 / 255, 0.0], 1.0];
        yield 'hex 4' => ['#F808', 'srgb', [1.0, 136 / 255, 0.0], 136 / 255];
        yield 'hex 6' => ['#3366cc', 'srgb', [51 / 255, 102 / 255, 204 / 255], 1.0];
        yield 'hex 8' => ['#3366CC80', 'srgb', [51 / 255, 102 / 255, 204 / 255], 128 / 255];
        yield 'named' => ['RebeccaPurple', 'srgb', [102 / 255, 51 / 255, 153 / 255], 1.0];
        yield 'transparent' => ['TRANSPARENT', 'srgb', [0.0, 0.0, 0.0], 0.0];
        yield 'comment starting with "/*/"' => ['/*/ red */ blue', 'srgb', [0.0, 0.0, 1.0], 1.0];
        yield 'surrounding whitespace and comments' => [" \t\n/* c */red\r\f ", 'srgb', [1.0, 0.0, 0.0], 1.0];

        // rgb()
        yield 'rgb modern numbers' => ['rgb(51 102 204)', 'srgb', [51 / 255, 102 / 255, 204 / 255], 1.0];
        yield 'rgb modern mixed' => ['rgb(20% 102 none / 50%)', 'srgb', [0.2, 102 / 255, null], 0.5];
        yield 'rgb clamps' => ['rgb(300 -5 127.5 / 2)', 'srgb', [1.0, 0.0, 0.5], 1.0];
        yield 'rgb alpha none' => ['rgb(0 0 0 / none)', 'srgb', [0.0, 0.0, 0.0], null];
        yield 'rgb alpha clamps below' => ['rgb(0 0 0 / -1)', 'srgb', [0.0, 0.0, 0.0], 0.0];
        yield 'rgba alias' => ['RGBA(0 0 0 / .25)', 'srgb', [0.0, 0.0, 0.0], 0.25];
        yield 'rgb legacy numbers' => ['rgb(51, 102, 204)', 'srgb', [51 / 255, 102 / 255, 204 / 255], 1.0];
        yield 'rgb legacy percentages + alpha %' => ['rgba( 20% ,40%,60% , 50% )', 'srgb', [0.2, 0.4, 0.6], 0.5];
        yield 'rgb legacy alpha number' => ['rgb(1, 2, 3, 0.5)', 'srgb', [1 / 255, 2 / 255, 3 / 255], 0.5];
        yield 'juxtaposed signed numbers' => ['rgb(1+2+3)', 'srgb', [1 / 255, 2 / 255, 3 / 255], 1.0];
        yield 'exponent and leading dot' => ['rgb(1e2 .5e1 +2.5E+1)', 'srgb', [100 / 255, 5 / 255, 25 / 255], 1.0];

        // hsl() and hwb()
        yield 'hsl modern' => ['hsl(220 60% 50%)', 'hsl', [220.0, 60.0, 50.0], 1.0];
        yield 'hsl modern numbers' => ['hsl(220deg 60 50 / 0.5)', 'hsl', [220.0, 60.0, 50.0], 0.5];
        yield 'hsl negative saturation clamps' => ['hsl(10 -20% 50%)', 'hsl', [10.0, 0.0, 50.0], 1.0];
        yield 'hsl lightness is not clamped' => ['hsl(10 20% 150%)', 'hsl', [10.0, 20.0, 150.0], 1.0];
        yield 'hsl none' => ['hsl(none none 50%)', 'hsl', [null, null, 50.0], 1.0];
        yield 'hsl legacy' => ['hsla(120, 100%, 25%, 0.5)', 'hsl', [120.0, 100.0, 25.0], 0.5];
        yield 'hsl legacy angle' => ['hsl(0.5turn, 10%, 20%)', 'hsl', [180.0, 10.0, 20.0], 1.0];
        yield 'hue grad' => ['hsl(100grad 10% 20%)', 'hsl', [90.0, 10.0, 20.0], 1.0];
        yield 'hue rad' => ['hsl(3.141592653589793rad 10% 20%)', 'hsl', [180.0, 10.0, 20.0], 1.0];
        yield 'hue kept as authored (Color normalises it)' => ['hsl(-540 10% 20%)', 'hsl', [-540.0, 10.0, 20.0], 1.0];
        yield 'hwb' => ['hwb(150 20% 10%)', 'hwb', [150.0, 20.0, 10.0], 1.0];
        yield 'hwb numbers, no clamping' => ['hwb(150 120 -10 / 30%)', 'hwb', [150.0, 120.0, -10.0], 0.3];

        // lab family
        yield 'lab percentages' => ['lab(50% 100% -100%)', 'lab', [50.0, 125.0, -125.0], 1.0];
        yield 'lab clamps L' => ['lab(-10 20 30)', 'lab', [0.0, 20.0, 30.0], 1.0];
        yield 'lab clamps L above' => ['lab(120 20 30)', 'lab', [100.0, 20.0, 30.0], 1.0];
        yield 'lch' => ['lch(50% 100% 30deg)', 'lch', [50.0, 150.0, 30.0], 1.0];
        yield 'lch clamps C' => ['lch(50 -10 30)', 'lch', [50.0, 0.0, 30.0], 1.0];
        yield 'oklab percentages' => ['oklab(50% 100% -100%)', 'oklab', [0.5, 0.4, -0.4], 1.0];
        yield 'oklab clamps L' => ['oklab(1.5 0 0)', 'oklab', [1.0, 0.0, 0.0], 1.0];
        yield 'oklab clamps L below' => ['oklab(-0.5 0 0)', 'oklab', [0.0, 0.0, 0.0], 1.0];
        yield 'oklch' => ['oklch(62% 50% 255)', 'oklch', [0.62, 0.2, 255.0], 1.0];
        yield 'oklch clamps C' => ['oklch(0.5 -0.1 none)', 'oklch', [0.5, 0.0, null], 1.0];

        // color()
        yield 'color srgb' => ['color(srgb 0.1 20% none)', 'srgb', [0.1, 0.2, null], 1.0];
        yield 'color srgb-linear' => ['color(srgb-linear 1 0 0)', 'srgb-linear', [1.0, 0.0, 0.0], 1.0];
        yield 'color display-p3 case' => ['COLOR(Display-P3 1.5 -0.5 0 / 50%)', 'display-p3', [1.5, -0.5, 0.0], 0.5];
        yield 'color display-p3-linear' => ['color(display-p3-linear 0 0 0)', 'display-p3-linear', [0.0, 0.0, 0.0], 1.0];
        yield 'color a98-rgb' => ['color(a98-rgb 0 0 0)', 'a98-rgb', [0.0, 0.0, 0.0], 1.0];
        yield 'color prophoto-rgb' => ['color(prophoto-rgb 0 0 0)', 'prophoto-rgb', [0.0, 0.0, 0.0], 1.0];
        yield 'color rec2020' => ['color(rec2020 0 0 0)', 'rec2020', [0.0, 0.0, 0.0], 1.0];
        yield 'color xyz alias' => ['color(xyz 0.5 0.5 0.5)', 'xyz-d65', [0.5, 0.5, 0.5], 1.0];
        yield 'color xyz-d65' => ['color(xyz-d65 50% 0 0)', 'xyz-d65', [0.5, 0.0, 0.0], 1.0];
        yield 'color xyz-d50' => ['color(xyz-d50 0 0 0)', 'xyz-d50', [0.0, 0.0, 0.0], 1.0];
    }

    /**
     * @param list<float|null> $coordinates
     */
    #[DataProvider('valid')]
    public function testParsesValidColors(string $css, string $spaceId, array $coordinates, ?float $alpha): void
    {
        $value = (new CssParser())->parse($css);

        self::assertSame($spaceId, $value->space->id());
        self::assertCount(3, $value->coordinates);
        foreach ($coordinates as $index => $expected) {
            if ($expected === null) {
                self::assertNull($value->coordinates[$index], $css);
            } else {
                self::assertNotNull($value->coordinates[$index], $css);
                self::assertFloatClose($expected, $value->coordinates[$index], Tolerances::linearAlgebra(), $css);
            }
        }

        if ($alpha === null) {
            self::assertNull($value->alpha, $css);
        } else {
            self::assertNotNull($value->alpha);
            self::assertFloatClose($alpha, $value->alpha, Tolerances::linearAlgebra(), $css);
        }
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function invalid(): iterable
    {
        yield 'empty' => ['', 0, 'empty'];
        yield 'only whitespace and a comment' => [' /* x */ ', 0, 'empty'];
        yield 'unterminated comment' => ['/* red', 0, 'empty'];
        yield 'number first' => ['5', 0, 'unexpected token'];
        yield 'close paren first' => [')', 0, 'unexpected token'];
        yield 'unexpected character' => ['rgb(1 2 3)!', 10, 'unexpected character "!"'];
        yield 'escape sequence' => ['r\\gb(1 2 3)', 1, 'unexpected character "\\"'];
        yield 'huge number' => ['rgb(1e999 0 0)', 4, 'out of range'];
        yield 'hex 5 digits' => ['#12345', 0, 'not a hex color'];
        yield 'hex non-hex digit' => ['#ffg', 0, 'not a hex color'];
        yield 'bare hash' => ['#', 0, 'unexpected character "#"'];
        yield 'hex followed by more' => ['#fff #000', 5, 'unexpected content'];
        yield 'unknown keyword' => ['bluish', 0, 'unknown color keyword "bluish"'];
        yield 'keyword followed by more' => ['red blue', 4, 'unexpected content'];
        yield 'unclosed function' => ['rgb(1 2 3', 9, 'not closed'];
        yield 'content after function' => ['rgb(1 2 3) x', 11, 'unexpected content'];
        yield 'second function after function' => ['rgb(1 2 3) rgb(1 2 3)', 11, 'unexpected content'];
        yield 'extra closing parenthesis' => ['rgb(1 2 3))', 10, 'unexpected content'];
        yield 'color-mix() space is not an identifier' => ['color-mix(in 5, red, blue)', 13, 'interpolation color space is missing'];
        yield 'relative syntax without origin' => ['rgb(from)', 4, 'the color is missing'];
        yield 'relative syntax without components' => ['rgb(from red)', 0, '0 component(s) given'];
        yield 'relative syntax with legacy commas' => ['rgb(from red r, g, b)', 14, 'commas are not allowed in rgb()'];
        yield 'relative syntax with calc()' => ['rgb(from red calc(r) g b)', 13, 'nested functions such as "calc()"'];
        yield 'relative syntax, unknown keyword' => ['rgb(from red r g h)', 17, 'invalid component'];
        yield 'relative syntax, keyword of another function' => ['lab(from red l c h)', 15, 'invalid component'];
        yield 'relative hue with a percentage' => ['hsl(from red 10% s l)', 13, 'invalid hue'];
        yield 'from in a function without relative syntax' => ['color-mix(from red, blue)', 10, 'unknown color keyword "from"'];
        yield 'from in an unknown function' => ['rgbx(from red r g b)', 5, '"rgbx()" has no relative color syntax'];
        yield 'alpha() without origin' => ['alpha(red / 0.5)', 6, 'alpha() requires an origin color'];
        yield 'alpha() empty' => ['alpha()', 0, 'alpha() requires an origin color'];
        yield 'alpha() with components' => ['alpha(from red r g b)', 15, 'alpha() takes only an alpha value'];
        yield 'alpha() with two values' => ['alpha(from red / 0.5 0.6)', 15, 'alpha() takes only an alpha value'];
        yield 'color-mix() without colors' => ['color-mix(in srgb)', 0, 'color-mix() has no colors'];
        yield 'color-mix() empty' => ['color-mix()', 0, 'empty argument'];
        yield 'color-mix() trailing comma' => ['color-mix(red, blue,)', 0, 'empty argument'];
        yield 'color-mix() missing comma' => ['color-mix(in srgb, red blue)', 23, 'unexpected content'];
        yield 'color-mix() missing space' => ['color-mix(in, red, blue)', 10, 'interpolation color space is missing'];
        yield 'color-mix() unknown space' => ['color-mix(in rgb, red, blue)', 13, 'unknown interpolation color space "rgb"'];
        yield 'color-mix() custom space' => ['color-mix(in --x, red, blue)', 13, 'custom color spaces'];
        yield 'color-mix() hue method in a rectangular space' => ['color-mix(in srgb longer hue, red, blue)', 18, '"srgb" has no hue'];
        yield 'color-mix() unknown hue method' => ['color-mix(in oklch shortest hue, red, blue)', 19, 'invalid hue interpolation method'];
        yield 'color-mix() hue method without "hue"' => ['color-mix(in oklch longer, red, blue)', 19, 'invalid hue interpolation method'];
        yield 'color-mix() hue method, wrong word' => ['color-mix(in oklch longer way, red, blue)', 19, 'invalid hue interpolation method'];
        yield 'color-mix() hue method, too many words' => ['color-mix(in oklch longer hue x, red, blue)', 19, 'invalid hue interpolation method'];
        yield 'color-mix() method not first' => ['color-mix(red, in srgb, blue)', 15, 'unknown color keyword "in"'];
        yield 'color-mix() negative percentage' => ['color-mix(red -10%, blue)', 14, 'range 0% to 100%'];
        yield 'color-mix() percentage above 100' => ['color-mix(red, 101% blue)', 15, 'range 0% to 100%'];
        yield 'color-mix() two percentages' => ['color-mix(10% red 20%, blue)', 18, 'unexpected content'];
        yield 'color-mix() number weight' => ['color-mix(red 0.5, blue)', 14, 'unexpected content'];
        yield 'color-mix() percentage only' => ['color-mix(50%, blue)', 10, 'the color is missing'];
        yield 'nested calc' => ['rgb(calc(1) 2 3)', 4, 'nested functions such as "calc()"'];
        yield 'unsupported function' => ['contrast-color(red)', 0, 'the function "contrast-color()" is not supported'];
        yield 'unknown function' => ['rgbx(1 2 3)', 0, 'unknown color function "rgbx()"'];
        yield 'legacy mixed types' => ['rgb(10%, 50, 0)', 4, 'cannot mix numbers and percentages'];
        yield 'legacy only the second differs' => ['rgb(10%, 50, 0%)', 4, 'cannot mix numbers and percentages'];
        yield 'legacy only the third differs' => ['rgb(10%, 50%, 0)', 4, 'cannot mix numbers and percentages'];
        yield 'legacy keyword in hsl' => ['hsl(0, 0%, light)', 11, 'requires percentages'];
        yield 'legacy none' => ['rgb(none, 0, 0)', 4, '"none" is not allowed'];
        yield 'legacy missing comma' => ['rgb(0, 0 0)', 9, 'exactly one comma'];
        yield 'legacy leading comma' => ['rgb(,0, 0, 0)', 4, 'exactly one comma'];
        yield 'legacy trailing comma' => ['rgb(0, 0, 0,)', 11, 'trailing comma'];
        yield 'legacy double comma' => ['rgb(0, 0,, 0)', 9, 'exactly one comma'];
        yield 'legacy too few' => ['rgb(0, 0)', 0, '2 value(s) given'];
        yield 'legacy too many' => ['rgba(0, 0, 0, 0, 0)', 0, '5 value(s) given'];
        yield 'legacy hsl number saturation' => ['hsl(0, 50, 30%)', 7, 'requires percentages'];
        yield 'legacy hsl number lightness' => ['hsl(0, 50%, 30)', 12, 'requires percentages'];
        yield 'legacy hsl percentage hue' => ['hsl(50%, 50%, 0%)', 4, 'invalid hue'];
        yield 'legacy angle in rgb' => ['rgb(0, 0, 0deg)', 4, 'cannot mix'];
        yield 'legacy dimension alpha' => ['rgb(0, 0, 0, 0deg)', 13, 'invalid alpha'];
        yield 'comma in hwb' => ['hwb(90, 50%, 50%)', 6, 'commas are not allowed in hwb()'];
        yield 'comma in color()' => ['color(srgb 0, 0, 0)', 12, 'commas are not allowed in color(srgb)'];
        yield 'modern too few' => ['lab(1 2)', 0, '2 component(s) given'];
        yield 'modern too few hsl' => ['hsl(1 2)', 0, 'hsl() with 3 components'];
        yield 'legacy too few hsla' => ['hsla(1, 2%)', 0, 'hsl() with 3 components'];
        yield 'modern too few hwb' => ['hwb(1 2)', 0, 'hwb() with 3 components'];
        yield 'modern too few lch' => ['lch(1 2)', 0, 'lch() with 3 components'];
        yield 'modern too few oklab' => ['oklab(1 2)', 0, 'oklab() with 3 components'];
        yield 'modern too few oklch' => ['oklch(1 2)', 0, 'oklch() with 3 components'];
        yield 'modern too few color()' => ['color(srgb 1 2)', 0, 'color(srgb) with 3 components'];
        yield 'modern too many' => ['lab(1 2 3 4)', 10, 'takes 3 components'];
        yield 'slash too early' => ['lab(1 2 / 3)', 8, 'misplaced "/"'];
        yield 'double slash' => ['lab(1 2 3 / / 4)', 12, 'misplaced "/"'];
        yield 'missing alpha' => ['lab(1 2 3 /)', 10, 'alpha value is missing'];
        yield 'two alphas' => ['lab(1 2 3 / 4 5)', 14, 'only one alpha'];
        yield 'keyword component' => ['lab(1 light 3)', 6, 'invalid component'];
        yield 'angle in lab' => ['lab(1 2 3deg)', 8, 'invalid component'];
        yield 'bad angle unit' => ['lch(1 2 3px)', 8, 'invalid hue'];
        yield 'keyword alpha' => ['lab(1 2 3 / bacon)', 12, 'invalid alpha'];
        yield 'color() without space' => ['color(1 1 1)', 6, 'must start with a color space'];
        yield 'color() empty' => ['color()', 0, 'must start with a color space'];
        yield 'unknown color space' => ['color(profoto-rgb 1 1 1)', 6, 'unknown color space "profoto-rgb"'];
        yield 'custom color space' => ['color(--acme 1 1 1)', 6, 'custom color spaces (@color-profile) are not supported'];
        yield 'non-CSS space name' => ['color(rec2020-linear 1 1 1)', 6, 'unknown color space'];
    }

    #[DataProvider('invalid')]
    public function testRejectsInvalidColors(string $css, int $offset, string $problem): void
    {
        try {
            (new CssParser())->parse($css);
            self::fail(\sprintf('"%s" must not parse.', $css));
        } catch (ColorParseException $exception) {
            self::assertSame($css, $exception->getInput());
            self::assertSame($offset, $exception->getOffset(), $exception->getMessage());
            self::assertStringContainsString($problem, $exception->getMessage());
            self::assertNotSame('', $exception->getExpected());
            self::assertStringContainsString('expected ' . $exception->getExpected(), $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unresolvable(): iterable
    {
        yield 'currentcolor' => ['currentColor', 'currentcolor'];
        yield 'system color' => ['CanvasText', 'canvastext'];
        yield 'last system color' => ['visitedtext', 'visitedtext'];
        yield 'deprecated system color' => ['ButtonHighlight', 'buttonhighlight'];
        yield 'last deprecated system color' => ['windowtext', 'windowtext'];
        yield 'light-dark' => ['light-dark(black, white)', 'light-dark'];
    }

    #[DataProvider('unresolvable')]
    public function testDocumentDependentColorsAreUnresolvable(string $css, string $keyword): void
    {
        try {
            (new CssParser())->parse($css);
            self::fail('Must not resolve.');
        } catch (UnresolvableCssColorException $exception) {
            self::assertSame($keyword, $exception->getKeyword());
            self::assertSame($css, $exception->getInput());
            self::assertStringContainsString(\sprintf('"%s"', $keyword), $exception->getMessage());
        }
    }

    public function testLegacySeparatorErrorsSayWhatWasExpected(): void
    {
        foreach (['rgb(0, 0 0)' => '","', 'rgb(,0, 0, 0)' => 'a value'] as $css => $expected) {
            try {
                (new CssParser())->parse($css);
                self::fail('Must not parse.');
            } catch (ColorParseException $exception) {
                self::assertSame($expected, $exception->getExpected(), $css);
                self::assertSame(0, $exception->getCode());
            }
        }
    }
}
