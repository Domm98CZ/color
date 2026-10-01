<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Notation;

use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\ColorValue;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Converter;

/**
 * Serialises a color as a CSS `<color>` (CSS Color Module Level 4 § 16, CRD 2026-09-26).
 *
 * The output always parses back to the same color (up to the chosen precision); values are never
 * clipped. The form depends on the color's space:
 *
 * - `srgb`, `hsl`, `hwb` without missing components: legacy `rgb(r, g, b)` / `rgba(r, g, b, a)`
 *   with 0–255 numbers, as CSS serialises sRGB colors. If a channel is outside [0, 1] (beyond
 *   {@see SRGB_TOLERANCE}), `color(srgb …)` is used instead, because the legacy form would be
 *   clamped when parsed.
 * - `srgb` with a missing component: `color(srgb …)` with `none`; `hsl`/`hwb` with a missing
 *   component: the modern `hsl()` / `hwb()` form with `none` (§ 16.2.2), except an `hsl` color
 *   with a negative saturation, which goes through sRGB (missing components become 0).
 * - `lab`, `lch`, `oklab`, `oklch`: their functions with plain numbers. If the lightness is outside
 *   the range the parser clamps to, or the chroma is negative, the color is written as
 *   `color(xyz-d50 …)` (Lab, LCh) or `color(xyz-d65 …)` (OKLab, OKLCh) instead.
 * - The predefined spaces (`srgb-linear`, `display-p3`, `display-p3-linear`, `a98-rgb`,
 *   `prophoto-rgb`, `rec2020`, `xyz-d50`, `xyz-d65`): `color(<space> …)`.
 * - Any other space (`hsv`, `lab-d65`, `hsl@display-p3`, user-defined spaces…) has no CSS form; the
 *   color is first converted, without clipping, to the nearest ancestor in its conversion tree that
 *   has one (e.g. `hsv` → `srgb`, `lab-d65` → `xyz-d65`), or to `xyz-d65` if there is none.
 *
 * Alpha is omitted when it is exactly 1, written as `none` when missing and otherwise as a number.
 */
final readonly class CssSerializer
{
    /**
     * An sRGB channel within this distance of [0, 1] is written in the legacy rgb() form (and
     * clamped to the range, a change of at most 2.6e-4 on the 0–255 scale). Equal to
     * `Color::DEFAULT_GAMUT_TOLERANCE`; far above conversion round-off, far below one 8-bit step.
     */
    public const float SRGB_TOLERANCE = 1e-6;

    private const array CSS_SPACES = [
        'srgb', 'hsl', 'hwb', 'lab', 'lch', 'oklab', 'oklch', 'srgb-linear', 'display-p3',
        'display-p3-linear', 'a98-rgb', 'prophoto-rgb', 'rec2020', 'xyz-d50', 'xyz-d65',
    ];

    public function __construct(private CssFormat $format = new CssFormat()) {}

    public function serialize(ColorValue $color): string
    {
        $space = self::cssSpace($color->space);
        $coordinates = $space === $color->space ? $color->coordinates : self::convert($color->space, $space, $color->coordinates);

        return match ($space->id()) {
            'srgb' => $this->srgb($coordinates, $color->alpha),
            'hsl', 'hwb' => $this->cylindrical($space, $coordinates, $color->alpha),
            'lab', 'lch' => $this->lab($space, $coordinates, $color->alpha, 100.0, ColorSpaces::xyzD50()),
            'oklab', 'oklch' => $this->lab($space, $coordinates, $color->alpha, 1.0, ColorSpaces::xyzD65()),
            default => $this->colorFunction($space->id(), $coordinates, $color->alpha),
        };
    }

    /**
     * @param list<float|null> $coordinates
     */
    private function srgb(array $coordinates, ?float $alpha): string
    {
        if ($alpha === null || \in_array(null, $coordinates, true)) {
            return $this->colorFunction('srgb', $coordinates, $alpha);
        }

        $channels = [];
        foreach ($coordinates as $value) {
            if ($value < -self::SRGB_TOLERANCE || $value > 1.0 + self::SRGB_TOLERANCE) {
                return $this->colorFunction('srgb', $coordinates, $alpha);
            }

            $channels[] = $this->number(\max(0.0, \min(1.0, $value)) * 255);
        }

        return $alpha === 1.0
            ? \sprintf('rgb(%s)', \implode(', ', $channels))
            : \sprintf('rgba(%s, %s)', \implode(', ', $channels), $this->number($alpha));
    }

    /**
     * @param list<float|null> $coordinates
     */
    private function cylindrical(ColorSpace $space, array $coordinates, ?float $alpha): string
    {
        // hsl() clamps a negative saturation when parsed; such a color goes through sRGB (where
        // missing components become 0) so that it is not changed.
        $negativeSaturation = $space->id() === 'hsl' && $coordinates[1] !== null && $coordinates[1] < 0.0;

        if ($negativeSaturation || ($alpha !== null && !\in_array(null, $coordinates, true))) {
            return $this->srgb(self::convert($space, ColorSpaces::srgb(), $coordinates), $alpha);
        }

        return \sprintf(
            '%s(%s %s %s%s)',
            $space->id(),
            $this->component($coordinates[0]),
            $this->component($coordinates[1], '%'),
            $this->component($coordinates[2], '%'),
            $this->alpha($alpha),
        );
    }

    /**
     * @param list<float|null> $coordinates
     */
    private function lab(ColorSpace $space, array $coordinates, ?float $alpha, float $maxLightness, ColorSpace $xyz): string
    {
        $lightness = $coordinates[0];
        $negativeChroma = \str_ends_with($space->id(), 'lch') && $coordinates[1] !== null && $coordinates[1] < 0.0;

        if (($lightness !== null && ($lightness < 0.0 || $lightness > $maxLightness)) || $negativeChroma) {
            return $this->colorFunction($xyz->id(), self::convert($space, $xyz, $coordinates), $alpha);
        }

        return \sprintf('%s(%s%s)', $space->id(), \implode(' ', \array_map($this->component(...), $coordinates)), $this->alpha($alpha));
    }

    /**
     * @param list<float|null> $coordinates
     */
    private function colorFunction(string $id, array $coordinates, ?float $alpha): string
    {
        return \sprintf('color(%s %s%s)', $id, \implode(' ', \array_map($this->component(...), $coordinates)), $this->alpha($alpha));
    }

    private function component(?float $value, string $unit = ''): string
    {
        return $value === null ? 'none' : $this->number($value) . $unit;
    }

    private function alpha(?float $alpha): string
    {
        return $alpha === 1.0 ? '' : ' / ' . $this->component($alpha);
    }

    private function number(float $value): string
    {
        return $this->format->number($value);
    }

    /**
     * The space itself if CSS can express it, otherwise its nearest such ancestor, otherwise xyz-d65.
     */
    private static function cssSpace(ColorSpace $space): ColorSpace
    {
        for ($candidate = $space; $candidate !== null; $candidate = $candidate->base()) {
            if (\in_array($candidate->id(), self::CSS_SPACES, true)) {
                return $candidate;
            }
        }

        return ColorSpaces::xyzD65();
    }

    /**
     * @param list<float|null> $coordinates
     *
     * @return list<float|null>
     */
    private static function convert(ColorSpace $from, ColorSpace $to, array $coordinates): array
    {
        return (new Converter())->convert($from, $to, $coordinates);
    }
}
