<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Difference\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * A parametric factor of a color-difference metric (kL, l, c…) is not a finite number > 0.
 */
final class InvalidMetricParameterException extends InvalidArgumentException
{
    /**
     * @internal validation helper of the metrics
     */
    public static function check(string $name, float $value): float
    {
        if (!\is_finite($value) || $value <= 0.0) {
            throw new self(\sprintf('The parameter %s must be a finite number > 0, %s given.', $name, \var_export($value, true)));
        }

        return $value;
    }
}
