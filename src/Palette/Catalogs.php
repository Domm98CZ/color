<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Notation\CssNamedColors;
use Domm98CZ\Color\Palette\Catalog\Catalog;

/**
 * Built-in catalogs.
 */
final class Catalogs
{
    private function __construct() {}

    /**
     * The 148 named colors of CSS Color 4 § 6.1, in alphabetical order, as sRGB. Synonyms (aqua and
     * cyan, fuchsia and magenta, the gray/grey pairs) are separate entries with the same color; the
     * nearest-color search returns the first of them.
     */
    public static function cssNamed(): Catalog
    {
        $colors = [];
        foreach (CssNamedColors::all() as $name => [$red, $green, $blue]) {
            $colors[$name] = Color::fromRgb($red, $green, $blue);
        }

        return Catalog::of('css-named-colors', $colors);
    }
}
