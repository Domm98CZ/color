<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Gamut;

use Domm98CZ\Color\Gamut\Algorithm\ChromaReduction;
use Domm98CZ\Color\Gamut\Algorithm\Clip;
use Domm98CZ\Color\Gamut\Algorithm\CssColor4BinarySearch;
use Domm98CZ\Color\Gamut\Algorithm\CssColor4RayTrace;

/**
 * Factories of the built-in gamut mappers. The mapper is always chosen explicitly.
 *
 *     GamutMappers::cssColor4()->map($p3Color, ColorSpaces::srgb())->color->toHex();
 *
 * The CSS Color 4 EdgeSeeker algorithm is not offered: the specification has no pseudocode for it
 * yet (§ 14.2.4 is a placeholder), only a reference implementation in another library.
 */
final class GamutMappers
{
    private function __construct() {}

    /**
     * CSS Color 4 binary search with local MINDE (the library's default recommendation).
     */
    public static function cssColor4(): CssColor4BinarySearch
    {
        return new CssColor4BinarySearch();
    }

    public static function rayTrace(): CssColor4RayTrace
    {
        return new CssColor4RayTrace();
    }

    public static function chromaReduction(): ChromaReduction
    {
        return new ChromaReduction();
    }

    public static function clip(): Clip
    {
        return new Clip();
    }
}
