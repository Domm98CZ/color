<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Compositing;

/**
 * The blend modes of W3C Compositing and Blending Level 1, § 10: B(Cb, Cs) for a backdrop and a
 * source color given as three RGB channels in [0, 1]. The value is the CSS `<blend-mode>` keyword.
 *
 * The blend functions are defined on the color range [0, 1]: channels outside it (out-of-gamut
 * colors) are clamped before blending, and the result of B is clamped to [0, 1] (§ 10: "must be
 * clamped to the minimum and maximum values of the color range"). The non-separable modes use the auxiliary functions of § 10.2 (Lum with
 * the weights 0.3, 0.59, 0.11, ClipColor, SetLum, Sat, SetSat) literally.
 */
enum BlendMode: string
{
    case Normal = 'normal';
    case Multiply = 'multiply';
    case Screen = 'screen';
    case Overlay = 'overlay';
    case Darken = 'darken';
    case Lighten = 'lighten';
    case ColorDodge = 'color-dodge';
    case ColorBurn = 'color-burn';
    case HardLight = 'hard-light';
    case SoftLight = 'soft-light';
    case Difference = 'difference';
    case Exclusion = 'exclusion';
    case Hue = 'hue';
    case Saturation = 'saturation';
    case Color = 'color';
    case Luminosity = 'luminosity';

    public function isSeparable(): bool
    {
        return !\in_array($this, [self::Hue, self::Saturation, self::Color, self::Luminosity], true);
    }

    /**
     * B(Cb, Cs) for inputs clamped to [0, 1]; the result is in [0, 1].
     *
     * @param array{float, float, float} $backdrop
     * @param array{float, float, float} $source
     *
     * @return array{float, float, float}
     */
    public function blend(array $backdrop, array $source): array
    {
        $backdrop = [self::clamp($backdrop[0]), self::clamp($backdrop[1]), self::clamp($backdrop[2])];
        $source = [self::clamp($source[0]), self::clamp($source[1]), self::clamp($source[2])];

        $result = match ($this) {
            self::Hue => self::setLum(self::setSat($source, self::sat($backdrop)), self::lum($backdrop)),
            self::Saturation => self::setLum(self::setSat($backdrop, self::sat($source)), self::lum($backdrop)),
            self::Color => self::setLum($source, self::lum($backdrop)),
            self::Luminosity => self::setLum($backdrop, self::lum($source)),
            default => [
                $this->separable($backdrop[0], $source[0]),
                $this->separable($backdrop[1], $source[1]),
                $this->separable($backdrop[2], $source[2]),
            ],
        };

        return [self::clamp($result[0]), self::clamp($result[1]), self::clamp($result[2])];
    }

    private function separable(float $cb, float $cs): float
    {
        return match ($this) {
            self::Multiply => $cb * $cs,
            self::Screen => self::screen($cb, $cs),
            self::Overlay => self::hardLight($cs, $cb),
            self::Darken => \min($cb, $cs),
            self::Lighten => \max($cb, $cs),
            self::ColorDodge => match (true) {
                $cb === 0.0 => 0.0,
                $cs === 1.0 => 1.0,
                default => \min(1.0, $cb / (1.0 - $cs)),
            },
            self::ColorBurn => match (true) {
                $cb === 1.0 => 1.0,
                $cs === 0.0 => 0.0,
                default => 1.0 - \min(1.0, (1.0 - $cb) / $cs),
            },
            self::HardLight => self::hardLight($cb, $cs),
            self::SoftLight => $cs <= 0.5
                ? $cb - (1.0 - 2.0 * $cs) * $cb * (1.0 - $cb)
                : $cb + (2.0 * $cs - 1.0) * (($cb <= 0.25 ? ((16.0 * $cb - 12.0) * $cb + 4.0) * $cb : \sqrt($cb)) - $cb),
            self::Difference => \abs($cb - $cs),
            self::Exclusion => $cb + $cs - 2.0 * $cb * $cs,
            default => $cs, // Normal
        };
    }

    private static function screen(float $cb, float $cs): float
    {
        return $cb + $cs - $cb * $cs;
    }

    private static function hardLight(float $cb, float $cs): float
    {
        return $cs <= 0.5 ? $cb * 2.0 * $cs : self::screen($cb, 2.0 * $cs - 1.0);
    }

    /**
     * @param array{float, float, float} $c
     */
    private static function lum(array $c): float
    {
        return 0.3 * $c[0] + 0.59 * $c[1] + 0.11 * $c[2];
    }

    /**
     * @param array{float, float, float} $c
     *
     * @return array{float, float, float}
     */
    private static function clipColor(array $c): array
    {
        $l = self::lum($c);
        $n = \min($c);
        $x = \max($c);

        if ($n < 0.0) {
            $c = [$l + ($c[0] - $l) * $l / ($l - $n), $l + ($c[1] - $l) * $l / ($l - $n), $l + ($c[2] - $l) * $l / ($l - $n)];
        }

        if ($x > 1.0) {
            $c = [$l + ($c[0] - $l) * (1.0 - $l) / ($x - $l), $l + ($c[1] - $l) * (1.0 - $l) / ($x - $l), $l + ($c[2] - $l) * (1.0 - $l) / ($x - $l)];
        }

        return $c;
    }

    /**
     * @param array{float, float, float} $c
     *
     * @return array{float, float, float}
     */
    private static function setLum(array $c, float $l): array
    {
        $d = $l - self::lum($c);

        return self::clipColor([$c[0] + $d, $c[1] + $d, $c[2] + $d]);
    }

    /**
     * @param array{float, float, float} $c
     */
    private static function sat(array $c): float
    {
        return \max($c) - \min($c);
    }

    /**
     * @param array{float, float, float} $c
     *
     * @return array{float, float, float}
     */
    private static function setSat(array $c, float $s): array
    {
        // Indexes of the minimum, middle and maximum component; equal values keep their order.
        $order = [0, 1, 2];
        \usort($order, static fn(int $a, int $b): int => $c[$a] <=> $c[$b]); // stable since PHP 8.0
        [$min, $mid, $max] = $order;

        $result = [0.0, 0.0, 0.0];
        if ($c[$max] > $c[$min]) {
            $result[$mid] = ($c[$mid] - $c[$min]) * $s / ($c[$max] - $c[$min]);
            $result[$max] = $s;
        }

        return $result;
    }

    private static function clamp(float $value): float
    {
        return \max(0.0, \min(1.0, $value));
    }
}
