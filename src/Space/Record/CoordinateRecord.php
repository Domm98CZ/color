<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Record;

use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Typed, immutable view of a color's coordinates in one built-in space.
 *
 * Non-hue channels are plain floats (a missing component reads as 0, as CSS treats it); hue channels
 * are `?float`, null meaning missing or powerless. Use `Color::from($record)` to get a Color and
 * `$color->in(Record::class)` to read one.
 */
interface CoordinateRecord
{
    public static function space(): ColorSpace;

    /**
     * @param list<float|null> $coordinates coordinates of {@see space()}, as produced by a conversion
     */
    public static function fromCoordinates(array $coordinates, float $alpha): static;

    /**
     * @return list<float|null>
     */
    public function coordinates(): array;

    public function alpha(): float;
}
