<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Colorimetry\Internal;

use Domm98CZ\Color\Colorimetry\WhitePoint;
use Domm98CZ\Color\Math\Matrix3;

/**
 * Von Kries scaling in a cone-like space M, with complete adaptation:
 * A = M⁻¹ · diag(M·W_to / M·W_from) · M. Equal whites give the identity exactly.
 *
 * The matrices are memoized (at most {@see CACHE_SIZE} pairs of whites per process): every
 * conversion between XYZ spaces with different whites asks for one, and computing it costs more
 * than the conversion itself. The cached matrix is the one computed, so results do not change.
 *
 * @internal
 */
final class ConeSpaceScaling
{
    public const int CACHE_SIZE = 64;

    /** @var array<string, Matrix3> */
    private static array $cache = [];

    private function __construct() {}

    /**
     * @param array{array{float, float, float}, array{float, float, float}, array{float, float, float}} $cone rows of M
     */
    public static function matrix(array $cone, WhitePoint $from, WhitePoint $to): Matrix3
    {
        if ($from->equals($to)) {
            return Matrix3::identity();
        }

        $key = \serialize([$cone, $from->xyz->toList(), $to->xyz->toList()]);
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        if (\count(self::$cache) >= self::CACHE_SIZE) {
            self::$cache = [];
        }

        $space = Matrix3::fromRows($cone);
        $source = $space->transform($from->xyz);
        $destination = $space->transform($to->xyz);

        return self::$cache[$key] = $space->inverse()
            ->multiply(Matrix3::diagonal($destination->x / $source->x, $destination->y / $source->y, $destination->z / $source->z))
            ->multiply($space);
    }

    /**
     * Forgets the memoized matrices (for tests).
     */
    public static function clear(): void
    {
        self::$cache = [];
    }

    /**
     * The number of memoized matrices (for tests).
     */
    public static function cached(): int
    {
        return \count(self::$cache);
    }
}
