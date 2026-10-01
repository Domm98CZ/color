<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space;

use Domm98CZ\Color\Math\Vector3;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Converts coordinates between color spaces through the conversion tree.
 *
 * 1. Walk from the source space towards its root until reaching a space that is also an ancestor
 *    of the target (spaces are identified by id).
 * 2. If there is no common ancestor, both roots are XYZ spaces: apply the chromatic adaptation from
 *    {@see ConversionOptions} between their white points (identity when the whites are equal).
 * 3. Walk down to the target.
 *
 * Missing components (null) are treated as zero during conversion (CSS Color 4 § 4.4); only the
 * final step may return null for a powerless hue. Values are never clipped.
 */
final readonly class Converter
{
    public function __construct(private ConversionOptions $options = new ConversionOptions()) {}

    /**
     * @param list<float|null> $coordinates
     *
     * @return list<float|null>
     */
    public function convert(ColorSpace $from, ColorSpace $to, array $coordinates): array
    {
        if ($from->id() === $to->id()) {
            return $coordinates;
        }

        $up = self::pathToRoot($from);
        $down = self::pathToRoot($to);
        $downIds = \array_map(static fn(ColorSpace $space): string => $space->id(), $down);

        $current = self::resolveMissing($coordinates);
        $commonIndexInDown = null;

        foreach ($up as $space) {
            $index = \array_search($space->id(), $downIds, true);
            if ($index !== false) {
                $commonIndexInDown = $index;
                break;
            }

            $current = $space->toBase($current);
        }

        if ($commonIndexInDown === null) {
            $sourceRoot = $up[\count($up) - 1];
            $targetRoot = $down[\count($down) - 1];
            $adaptation = $this->options->adaptation->matrix($sourceRoot->whitePoint(), $targetRoot->whitePoint());
            $current = $adaptation->transform(Vector3::fromList($current))->toList();
            $commonIndexInDown = \count($down) - 1;
        }

        $result = $current;
        for ($i = $commonIndexInDown - 1; $i >= 0; $i--) {
            $result = $down[$i]->fromBase(self::resolveMissing($result));
        }

        return $result;
    }

    /**
     * @return non-empty-list<ColorSpace> the space itself first, its root last
     */
    private static function pathToRoot(ColorSpace $space): array
    {
        $path = [$space];

        while (($space = $space->base()) !== null) {
            $path[] = $space;
        }

        return $path;
    }

    /**
     * @param list<float|null> $coordinates
     *
     * @return list<float>
     */
    private static function resolveMissing(array $coordinates): array
    {
        return \array_map(static fn(?float $value): float => $value ?? 0.0, $coordinates);
    }
}
