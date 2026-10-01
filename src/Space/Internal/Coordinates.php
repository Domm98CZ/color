<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Internal;

use Domm98CZ\Color\Math\Angle;
use Domm98CZ\Color\Math\Numeric;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Exception\InvalidColorValueException;

/**
 * @internal validation and normalisation of coordinates and alpha
 */
final class Coordinates
{
    private function __construct() {}

    /**
     * Validates coordinates against a space: exact channel count, finite numbers or null (missing).
     * Hue channels are normalised to [0, 360); -0.0 becomes 0.0.
     *
     * @param array<array-key, mixed> $coordinates
     *
     * @return list<float|null>
     */
    public static function normalize(ColorSpace $space, array $coordinates): array
    {
        $channels = $space->channels();

        if (!\array_is_list($coordinates) || \count($coordinates) !== \count($channels)) {
            throw new InvalidColorValueException(\sprintf(
                'Color space "%s" expects a list of %d coordinates, %s given.',
                $space->id(),
                \count($channels),
                \array_is_list($coordinates) ? (string) \count($coordinates) : 'a non-list array',
            ));
        }

        $normalized = [];
        foreach ($channels as $index => $channel) {
            $value = $coordinates[$index];

            if ($value === null) {
                $normalized[] = null;
                continue;
            }

            if (!\is_int($value) && !\is_float($value)) {
                throw new InvalidColorValueException(\sprintf('Coordinate "%s" of "%s" must be a number or null, %s given.', $channel->name, $space->id(), \get_debug_type($value)));
            }

            $number = self::finite((float) $value, \sprintf('Coordinate "%s" of "%s"', $channel->name, $space->id()));
            $normalized[] = $channel->isHue() ? Angle::normalizeDegrees($number) : Numeric::normalizeZero($number);
        }

        return $normalized;
    }

    public static function alpha(float $alpha): float
    {
        if (!\is_finite($alpha) || $alpha < 0.0 || $alpha > 1.0) {
            throw new InvalidColorValueException(\sprintf('Alpha must be a number in [0, 1], %s given.', \var_export($alpha, true)));
        }

        return Numeric::normalizeZero($alpha);
    }

    public static function finite(float $value, string $name): float
    {
        if (!\is_finite($value)) {
            throw new InvalidColorValueException(\sprintf('%s must be a finite number, %s given.', $name, \var_export($value, true)));
        }

        return Numeric::normalizeZero($value);
    }

    public static function hue(?float $hue): ?float
    {
        return $hue === null ? null : Angle::normalizeDegrees(self::finite($hue, 'Hue'));
    }
}
