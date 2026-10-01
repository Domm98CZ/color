<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Notation\Internal;

/**
 * @internal formats numbers for CSS (CSS Color 4 § 16)
 *
 * Base ten, "." as the decimal separator regardless of locale, no exponent, the leading zero kept,
 * trailing zeros and a trailing "." removed, "-0" written as "0". Rounding to the given number of
 * decimal places is half towards +∞ ("rounded towards +∞, not truncated"), applied to the binary64
 * value (0.285 is 0.28499999999999998… and rounds to 0.28).
 */
final class DecimalFormatter
{
    private const float EXACT_INTEGER_LIMIT = 9007199254740992.0; // 2^53

    private function __construct() {}

    public static function format(float $value, int $fractionDigits): string
    {
        $scaled = $value * 10 ** $fractionDigits;

        if (\abs($scaled) >= self::EXACT_INTEGER_LIMIT) {
            // No binary64 value this large has digits below the requested precision to round.
            return self::trim(\sprintf('%.' . $fractionDigits . 'F', $value));
        }

        $rounded = \floor($scaled + 0.5);
        $digits = \sprintf('%.0F', \abs($rounded));

        if ($fractionDigits > 0) {
            $digits = \str_pad($digits, $fractionDigits + 1, '0', \STR_PAD_LEFT);
            $digits = \substr($digits, 0, -$fractionDigits) . '.' . \substr($digits, -$fractionDigits);
        }

        $text = self::trim($digits);

        return $rounded < 0.0 && $text !== '0' ? '-' . $text : $text;
    }

    private static function trim(string $number): string
    {
        if (\str_contains($number, '.')) {
            $number = \rtrim(\rtrim($number, '0'), '.');
        }

        return $number === '-0' ? '0' : $number;
    }
}
