<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Palette;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Difference\DeltaE;
use Domm98CZ\Color\Palette\Catalog\Catalog;
use Domm98CZ\Color\Palette\Catalogs;
use Domm98CZ\Color\Palette\Exception\InvalidPaletteException;
use Domm98CZ\Color\Palette\Palette;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaletteAndCatalogTest extends TestCase
{
    public function testPaletteBasics(): void
    {
        $red = Color::fromHex('#f00');
        $blue = Color::fromHex('#00f');
        $palette = Palette::named(['primary' => $red, 'accent' => $blue]);

        self::assertCount(2, $palette);
        self::assertSame([$red, $blue], $palette->colors());
        self::assertSame($blue, $palette->get('accent'));
        self::assertSame($red, $palette->get(0));
        self::assertSame(['primary', 'accent'], \array_map(static fn($e): ?string => $e->name, $palette->entries()));

        $more = $palette->with(Color::fromHex('#0f0'));
        self::assertCount(3, $more);
        self::assertCount(2, $palette, 'immutable');
        self::assertNull($more->entries()[2]->name);

        $renamed = Palette::of($red, $blue)->withNames(['500', '600']);
        self::assertSame($blue, $renamed->get('600'));
        self::assertSame([$red], Palette::of(...['x' => $red])->colors(), 'named variadics become a list');
    }

    /**
     * @return iterable<string, array{\Closure(): mixed, string}>
     */
    public static function invalid(): iterable
    {
        $red = Color::fromHex('#f00');

        yield 'duplicate name' => [static fn() => Palette::named(['a' => $red])->with($red, 'a'), 'is used twice'];
        yield 'duplicate name after an unnamed entry' => [static fn() => Palette::of($red)->with($red, 'a')->with($red, 'a'), 'is used twice'];
        yield 'blank name' => [static fn() => Palette::of($red)->withNames([' ']), 'must not be empty'];
        yield 'unknown name' => [static fn() => Palette::of($red)->get('x'), 'no entry "x"'];
        yield 'unknown index' => [static fn() => Palette::of($red)->get(1), 'no entry #1'];
        yield 'wrong number of names' => [static fn() => Palette::of($red)->withNames(['a', 'b']), '2 names given for 1 colors'];
        yield 'empty catalog' => [static fn() => Catalog::of('x', []), 'at least one color'];
        yield 'rank limit 0' => [static fn() => Catalogs::cssNamed()->rank($red, DeltaE::ok(), 0), 'at least 1, 0 given'];
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

    public function testCssNamedCatalog(): void
    {
        $catalog = Catalogs::cssNamed();

        self::assertSame('css-named-colors', $catalog->id());
        self::assertCount(148, $catalog->entries());
        self::assertSame('aliceblue', $catalog->entries()[0]->name);
        self::assertSame('#663399', $catalog->nearest(Color::fromHex('#663399'), DeltaE::ciede2000())->entry->color->toHex());
        self::assertSame('rebeccapurple', $catalog->nearest(Color::fromHex('#663399'), DeltaE::ciede2000())->entry->name);
        self::assertSame(0.0, $catalog->nearest(Color::fromHex('#663399'), DeltaE::ciede2000())->difference->value);
    }

    public function testSynonymsResolveToTheFirstEntry(): void
    {
        $catalog = Catalogs::cssNamed();

        self::assertSame('aqua', $catalog->nearest(Color::fromHex('#00ffff'), DeltaE::ok())->entry->name);
        self::assertSame(['aqua', 'cyan'], \array_map(static fn($m): string => $m->entry->name, $catalog->rank(Color::fromHex('#00ffff'), DeltaE::ok(), 2)));
        self::assertSame('darkgray', $catalog->nearest(Color::fromHex('#a9a9a9'), DeltaE::ok())->entry->name);
    }

    public function testRankIsSortedAndUsesTheSearchedColorAsReference(): void
    {
        $catalog = Catalog::of('mine', ['far' => Color::fromHex('#000'), 'near' => Color::fromHex('#3a6bd0'), 'mid' => Color::fromHex('#6699cc')]);
        $target = Color::fromHex('#3366cc');

        $ranked = $catalog->rank($target, DeltaE::ciede2000());
        self::assertSame(['near', 'mid', 'far'], \array_map(static fn($m): string => $m->entry->name, $ranked));
        self::assertSame(DeltaE::ciede2000()->difference($target, Color::fromHex('#3a6bd0'))->value, $ranked[0]->difference->value);
        self::assertCount(1, $catalog->rank($target, DeltaE::ok(), 1));
        self::assertCount(5, Catalogs::cssNamed()->rank($target, DeltaE::ok()), 'five matches by default');
        self::assertSame('mine', $catalog->id());
    }
}
