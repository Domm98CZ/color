<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Math;

use Domm98CZ\Color\Math\Exception\NonFiniteValueException;
use Domm98CZ\Color\Math\Exception\OutOfDomainException;

/**
 * Small numeric helpers used across the library.
 */
final class Numeric
{
    private function __construct() {}

    /**
     * Real, sign-preserving cube root: cbrt(-8) = -2.
     *
     * PHP has no native cbrt(); `x ** (1/3)` is NaN for negative x, and `1/3` is not exactly
     * representable. The initial estimate is therefore refined by one Newton–Raphson step,
     * which brings the result to within about one ulp of the true root.
     */
    public static function cbrt(float $x): float
    {
        self::assertFinite($x, 'Cube root argument');

        if ($x === 0.0) {
            return 0.0;
        }

        $magnitude = \abs($x);
        $y = $magnitude ** (1.0 / 3.0);
        $y -= ($y * $y * $y - $magnitude) / (3.0 * $y * $y);

        return $x < 0.0 ? -$y : $y;
    }

    /**
     * Sign-preserving power: sign(base) · |base|^exponent.
     *
     * Used where a transfer function is mirrored for negative (out-of-gamut) values,
     * e.g. the extended sRGB transfer function in CSS Color 4. Zero (including -0.0) maps to +0.0.
     *
     * @throws OutOfDomainException when the base is zero and the exponent is not positive (division by zero)
     */
    public static function signedPow(float $base, float $exponent): float
    {
        self::assertFinite($base, 'Base');
        self::assertFinite($exponent, 'Exponent');

        if ($base === 0.0 && $exponent <= 0.0) {
            throw new OutOfDomainException(\sprintf('Zero cannot be raised to the non-positive power %s.', \var_export($exponent, true)));
        }

        $result = \abs($base) ** $exponent;

        return $base < 0.0 ? -$result : $result;
    }

    /**
     * Replaces negative zero with positive zero; other values are returned unchanged.
     *
     * Keeps equality checks and serialisation deterministic.
     */
    public static function normalizeZero(float $x): float
    {
        return $x === 0.0 ? 0.0 : $x;
    }

    /**
     * Returns the value if it is finite, otherwise throws.
     *
     * @throws NonFiniteValueException
     */
    public static function assertFinite(float $value, string $name): float
    {
        if (!\is_finite($value)) {
            throw NonFiniteValueException::for($name, $value);
        }

        return $value;
    }
}
