<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Notation;

use Domm98CZ\Color\Notation\Exception\InvalidCssFormatException;
use Domm98CZ\Color\Notation\Internal\DecimalFormatter;

/**
 * Serialisation policy of {@see CssSerializer}.
 *
 * The only choice is the precision: every number is rounded to `fractionDigits` decimal places
 * (half towards +∞, as CSS Color 4 § 16 requires), then trailing zeros are removed. The default
 * of 6 meets the minimum precision CSS Color 4 § 16 requires for every form (alpha: 6 decimal
 * places; Lab: 16 bits over 0–100; OKLab: 16 bits over ±0.5; color(xyz): 16 bits).
 */
final readonly class CssFormat
{
    public const int DEFAULT_FRACTION_DIGITS = 6;
    public const int MAX_FRACTION_DIGITS = 15;

    /**
     * @throws InvalidCssFormatException
     */
    public function __construct(public int $fractionDigits = self::DEFAULT_FRACTION_DIGITS)
    {
        if ($fractionDigits < 0 || $fractionDigits > self::MAX_FRACTION_DIGITS) {
            throw new InvalidCssFormatException(\sprintf('fractionDigits must be between 0 and %d, %d given.', self::MAX_FRACTION_DIGITS, $fractionDigits));
        }
    }

    /**
     * A CSS number in this format: base ten, no exponent, rounded half towards +∞ to
     * `fractionDigits` places, no trailing zeros (e.g. for gradient positions and angles).
     */
    public function number(float $value): string
    {
        return DecimalFormatter::format($value, $this->fractionDigits);
    }
}
