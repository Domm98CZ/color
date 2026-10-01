<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Notation;

use Domm98CZ\Color\Notation\CssNamedColors;
use PHPUnit\Framework\TestCase;

final class CssNamedColorsTest extends TestCase
{
    public function testTheTableHasAllNamedColorsOfCssColor4(): void
    {
        $all = CssNamedColors::all();
        $names = \array_keys($all);
        $sorted = $names;
        \sort($sorted);

        self::assertCount(148, $all);
        self::assertSame($sorted, $names, 'alphabetical, as in the specification');
        self::assertSame('aliceblue', $names[0]);
        self::assertSame('yellowgreen', $names[147]);
        self::assertArrayNotHasKey('transparent', $all);

        foreach ($all as $name => $rgb) {
            self::assertSame(\strtolower($name), $name);
            self::assertCount(3, $rgb);
        }
    }

    public function testKnownValues(): void
    {
        self::assertSame([102, 51, 153], CssNamedColors::get('rebeccapurple'));
        self::assertSame([218, 165, 32], CssNamedColors::get('goldenrod'));
        self::assertSame([255, 0, 255], CssNamedColors::get('magenta'));
        self::assertSame(CssNamedColors::get('fuchsia'), CssNamedColors::get('magenta'));
        self::assertSame(CssNamedColors::get('aqua'), CssNamedColors::get('cyan'));

        foreach (['gray', 'darkgray', 'darkslategray', 'dimgray', 'lightgray', 'lightslategray', 'slategray'] as $gray) {
            self::assertSame(CssNamedColors::get($gray), CssNamedColors::get(\str_replace('gray', 'grey', $gray)), $gray);
        }
    }

    public function testLookupIsCaseInsensitive(): void
    {
        self::assertSame([240, 248, 255], CssNamedColors::get('AliceBlue'));
        self::assertTrue(CssNamedColors::has('YELLOWGREEN'));
        self::assertFalse(CssNamedColors::has('transparent'));
        self::assertNull(CssNamedColors::get('bluish'));
    }
}
