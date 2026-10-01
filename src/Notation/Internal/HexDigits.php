<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Notation\Internal;

/**
 * @internal decoding of the digits of a CSS hex color (CSS Color 4 § 5.2)
 */
final class HexDigits
{
    private function __construct() {}

    /**
     * @return array{float, float, float, float}|null sRGB red, green, blue in [0, 1] and alpha; null when
     *                                                the digits are not 3, 4, 6 or 8 hexadecimal digits
     */
    public static function decode(string $digits): ?array
    {
        $length = \strlen($digits);

        if (!\in_array($length, [3, 4, 6, 8], true) || !\ctype_xdigit($digits)) {
            return null;
        }

        if ($length <= 4) {
            $digits = \preg_replace('/(.)/', '$1$1', $digits) ?? $digits;
        }

        $bytes = \array_map(static fn(string $pair): int => (int) \hexdec($pair), \str_split($digits, 2));

        return [$bytes[0] / 255.0, $bytes[1] / 255.0, $bytes[2] / 255.0, ($bytes[3] ?? 255) / 255.0];
    }
}
