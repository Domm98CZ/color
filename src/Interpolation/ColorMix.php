<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Interpolation;

use Domm98CZ\Color\Interpolation\Exception\InvalidInterpolationException;
use Domm98CZ\Color\Space\ColorValue;

/**
 * The result of CSS `color-mix()` (CSS Color 5, WD 13 September 2026, § 3.3), with the percentage
 * normalisation of CSS Values 5 § 6.1 (Editor's Draft, retrieved 2026-09-28).
 *
 * 1. Normalise the percentages with forced normalisation:
 *    - the specified sum (clamped to 100 %) is shared equally by the items without a percentage;
 *    - if the total is above 100 %, or above 0 %, every percentage is scaled to sum to 100 %;
 *    - the leftover is 100 % − total when the total is below 100 %, else 0.
 * 2. One item: its color converted to the interpolation space. More items: the first two are
 *    interpolated with progress = (second percentage) / (their sum), or 0.5 when the sum is 0;
 *    the result, with the summed percentage, is mixed with the next item, and so on.
 * 3. The alpha of the result is multiplied by 1 − leftover. A missing alpha stays missing (CSS does
 *    not define the product of `none`; library decision).
 *
 * In spaces with a hue, the order of the items matters (§ 3.3 note).
 */
final class ColorMix
{
    private function __construct() {}

    /**
     * @param list<MixItem> $items
     *
     * @throws InvalidInterpolationException when there are no items
     */
    public static function mix(InterpolationMethod $method, array $items): ColorValue
    {
        if ($items === []) {
            throw new InvalidInterpolationException('color-mix() needs at least one color.');
        }

        [$percentages, $leftover] = self::normalize($items);

        $color = MissingComponents::convert($items[0]->color, $method->space, $method->conversion);
        $weight = $percentages[0];

        for ($index = 1, $count = \count($items); $index < $count; $index++) {
            $combined = $weight + $percentages[$index];
            $progress = $combined > 0.0 ? $percentages[$index] / $combined : 0.5;
            $color = Segment::between($color, $items[$index]->color, $method)->at($progress);
            $weight = $combined;
        }

        if ($leftover === 0.0 || $color->alpha === null) {
            return $color;
        }

        return new ColorValue($color->space, $color->coordinates, $color->alpha * (1.0 - $leftover));
    }

    /**
     * Normalising mix percentages (CSS Values 5 § 6.1) with the force normalisation flag set.
     *
     * @param non-empty-list<MixItem> $items
     *
     * @return array{non-empty-list<float>, float} the percentages and the leftover, as fractions
     */
    public static function normalize(array $items): array
    {
        $specified = 0.0;
        $omitted = 0;

        foreach ($items as $item) {
            if ($item->percentage === null) {
                $omitted++;
            } else {
                $specified += $item->percentage;
            }
        }

        // Used only for omitted percentages, so the divisor is never 0 where it matters.
        $share = (1.0 - \min(1.0, $specified)) / \max(1, $omitted);
        $percentages = \array_map(static fn(MixItem $item): float => $item->percentage ?? $share, $items);
        $total = \array_sum($percentages);

        if ($total > 0.0) {
            $percentages = \array_map(static fn(float $percentage): float => $percentage / $total, $percentages);
        }

        return [$percentages, \max(0.0, 1.0 - $total)];
    }
}
