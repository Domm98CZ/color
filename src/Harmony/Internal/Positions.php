<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Harmony\Internal;

use Domm98CZ\Color\Harmony\ColorOrder;
use Domm98CZ\Color\Harmony\Result\WheelClassification;
use Domm98CZ\Color\Harmony\WheelPosition;
use Domm98CZ\Color\Math\Angle;
use Domm98CZ\Color\Provenance\AlgorithmId;

/**
 * @internal the twelve positions of a wheel with three primaries at 0°, 120° and 240°
 */
final class Positions
{
    private function __construct() {}

    /**
     * @param array{non-empty-string, non-empty-string, non-empty-string} $primaries at 0°, 120°, 240°
     * @param array{non-empty-string, non-empty-string, non-empty-string} $secondaries at 60°, 180°, 300°
     *
     * @return list<WheelPosition>
     */
    public static function of(array $primaries, array $secondaries): array
    {
        [$p0, $p1, $p2] = $primaries;
        [$s0, $s1, $s2] = $secondaries;

        // Tertiaries are named after their primary first, then the secondary (Itten's convention).
        return [
            new WheelPosition($p0, 0.0, ColorOrder::Primary),
            new WheelPosition($p0 . '-' . $s0, 30.0, ColorOrder::Tertiary),
            new WheelPosition($s0, 60.0, ColorOrder::Secondary),
            new WheelPosition($p1 . '-' . $s0, 90.0, ColorOrder::Tertiary),
            new WheelPosition($p1, 120.0, ColorOrder::Primary),
            new WheelPosition($p1 . '-' . $s1, 150.0, ColorOrder::Tertiary),
            new WheelPosition($s1, 180.0, ColorOrder::Secondary),
            new WheelPosition($p2 . '-' . $s1, 210.0, ColorOrder::Tertiary),
            new WheelPosition($p2, 240.0, ColorOrder::Primary),
            new WheelPosition($p2 . '-' . $s2, 270.0, ColorOrder::Tertiary),
            new WheelPosition($s2, 300.0, ColorOrder::Secondary),
            new WheelPosition($p0 . '-' . $s2, 330.0, ColorOrder::Tertiary),
        ];
    }

    /**
     * The nearest position; halfway between two positions (15° off) the later one is taken.
     *
     * @param list<WheelPosition> $positions
     */
    public static function classify(array $positions, ?float $hue, AlgorithmId $wheel): WheelClassification
    {
        if ($hue === null) {
            return new WheelClassification(ColorOrder::Achromatic, null, null, null, $wheel);
        }

        $position = $positions[(int) \floor($hue / 30.0 + 0.5) % 12];

        return new WheelClassification($position->order, $position, $hue, Angle::distanceDegrees($hue, $position->angle), $wheel);
    }
}
