<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Interpolation;

use Domm98CZ\Color\Space\ColorValue;
use Domm98CZ\Color\Space\ComponentCategory;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\ConversionOptions;
use Domm98CZ\Color\Space\Converter;

/**
 * Conversion that carries missing components forward (CSS Color 4 § 13.3, CRD 2026-09-26).
 *
 * A plain conversion treats a missing component (`none`) as 0. Interpolation, `color-mix()` and
 * relative color syntax instead keep it missing when the target space has an analogous component
 * ({@see ComponentCategory}):
 *
 * - a missing component whose category also exists in the target space makes that target
 *   component missing (e.g. the hue of `lch(50% 0.02 none)` in OKLCh);
 * - the channels left after removing the analogous ones form an analogous set in each space; when
 *   every channel of the source set is missing, every channel of the target set is missing
 *   (`lab(50% none none)` in LCh is `lch(50% none none)`);
 * - the alpha is always carried as it is.
 *
 * A hue that is powerless after the conversion is missing as well ({@see Converter}).
 */
final class MissingComponents
{
    private function __construct() {}

    public static function convert(ColorValue $color, ColorSpace $target, ConversionOptions $options = new ConversionOptions()): ColorValue
    {
        if ($color->space->id() === $target->id()) {
            return $color;
        }

        $coordinates = (new Converter($options))->convert($color->space, $target, $color->coordinates);
        $sourceCategories = self::categories($color->space);
        $targetCategories = self::categories($target);
        $sourceRest = [];
        $targetRest = [];

        foreach ($sourceCategories as $index => $category) {
            if ($category === null || !\in_array($category, $targetCategories, true)) {
                $sourceRest[] = $index;
            }
        }

        $missing = [];
        foreach ($targetCategories as $index => $category) {
            $source = $category === null ? false : \array_search($category, $sourceCategories, true);

            if ($source === false) {
                $targetRest[] = $index;
            } elseif ($color->coordinates[$source] === null) {
                $missing[] = $index;
            }
        }

        if ($sourceRest !== [] && self::allMissing($color, $sourceRest)) {
            $missing = [...$missing, ...$targetRest];
        }

        $result = [];
        foreach ($coordinates as $index => $value) {
            $result[] = \in_array($index, $missing, true) ? null : $value;
        }

        return new ColorValue($target, $result, $color->alpha);
    }

    /**
     * @return list<ComponentCategory|null>
     */
    private static function categories(ColorSpace $space): array
    {
        $categories = [];
        foreach ($space->channels() as $channel) {
            $categories[] = $channel->category;
        }

        return $categories;
    }

    /**
     * @param non-empty-list<int> $indexes
     */
    private static function allMissing(ColorValue $color, array $indexes): bool
    {
        foreach ($indexes as $index) {
            if ($color->coordinates[$index] !== null) {
                return false;
            }
        }

        return true;
    }
}
