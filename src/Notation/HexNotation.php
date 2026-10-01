<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Notation;

use Domm98CZ\Color\Notation\Exception\ColorParseException;
use Domm98CZ\Color\Notation\Exception\OutOfGamutException;
use Domm98CZ\Color\Notation\Internal\HexDigits;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\ColorValue;
use Domm98CZ\Color\Space\Converter;

/**
 * Hexadecimal sRGB notation `#rrggbb[aa]` / `#rgb[a]` (CSS Color 4 § 5.2).
 */
final readonly class HexNotation
{
    /**
     * An sRGB channel within this distance of [0, 1] still counts as in gamut (same value as
     * `Color::DEFAULT_GAMUT_TOLERANCE`); it is at most 2.6e-4 of one 8-bit step, so it never changes
     * the written byte by more than the rounding already does.
     */
    public const float GAMUT_TOLERANCE = 1e-6;

    /**
     * Reads 3, 4, 6 or 8 hexadecimal digits, case-insensitive, with an optional leading "#".
     * No whitespace is allowed. The result is in `srgb`.
     *
     * @throws ColorParseException
     */
    public function parse(string $hex): ColorValue
    {
        $digits = \str_starts_with($hex, '#') ? \substr($hex, 1) : $hex;
        $rgba = HexDigits::decode($digits);

        if ($rgba === null) {
            throw new ColorParseException($hex, \str_starts_with($hex, '#') ? 1 : 0, '3, 4, 6 or 8 hexadecimal digits', 'not a hex color');
        }

        return new ColorValue(ColorSpaces::srgb(), [$rgba[0], $rgba[1], $rgba[2]], $rgba[3]);
    }

    /**
     * Writes `#rrggbb`, or `#rrggbbaa` when the alpha byte is not ff (or when asked to).
     * Lowercase. Each channel is rounded to the nearest byte (half towards +∞). Missing
     * components, including a missing alpha, are written as 0 (CSS Color 4 § 4.4).
     *
     * @throws OutOfGamutException when the color is outside sRGB (beyond {@see GAMUT_TOLERANCE})
     */
    public function format(ColorValue $color, bool $alwaysIncludeAlpha = false): string
    {
        $srgb = ColorSpaces::srgb();
        $coordinates = $color->space->id() === $srgb->id()
            ? $color->coordinates
            : (new Converter())->convert($color->space, $srgb, $color->coordinates);

        $hex = '#';
        foreach ($coordinates as $value) {
            $value ??= 0.0;

            if ($value < -self::GAMUT_TOLERANCE || $value > 1.0 + self::GAMUT_TOLERANCE) {
                throw new OutOfGamutException($color->space, $color->coordinates, $srgb, \sprintf(
                    'The color %s is outside the sRGB gamut and cannot be written as HEX without changing it. Map it into sRGB explicitly, or write it with toCss(), which keeps out-of-gamut values.',
                    (new CssSerializer())->serialize($color),
                ));
            }

            $hex .= self::byte($value);
        }

        $alpha = self::byte($color->alpha ?? 0.0);

        return $alwaysIncludeAlpha || $alpha !== 'ff' ? $hex . $alpha : $hex;
    }

    private static function byte(float $value): string
    {
        return \sprintf('%02x', (int) \floor(\max(0.0, \min(1.0, $value)) * 255 + 0.5));
    }
}
