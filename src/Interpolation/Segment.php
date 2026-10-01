<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Interpolation;

use Domm98CZ\Color\Interpolation\Exception\InvalidInterpolationException;
use Domm98CZ\Color\Math\Angle;
use Domm98CZ\Color\Space\ColorValue;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Interpolation between two colors, CSS Color 4 § 13 (CRD 2026-09-26).
 *
 * {@see between()} performs the preparation once:
 *
 * 1. both colors are converted to the interpolation space, carrying missing analogous components
 *    forward ({@see MissingComponents}); a hue that becomes powerless is missing;
 * 2. a component (or alpha) missing in one color takes the value of the other color; missing in
 *    both, it stays missing and the result is missing it too;
 * 3. hue channels are fixed up by the {@see HueInterpolation} method;
 * 4. components are premultiplied by alpha (not the hue; not when the alpha is missing).
 *    When both alphas are equal, premultiplication cancels out exactly in real arithmetic and is
 *    skipped, so that opaque colors are not disturbed by rounding (x·α/α).
 *
 * {@see at()} then interpolates each component as Cs × (1 − p) + Ce × p (§ 13.1) and undoes the
 * premultiplication (not when the interpolated alpha is 0 or missing). Values outside the gamut of
 * the interpolation space are interpolated as they are, never clipped (§ 13.2).
 */
final readonly class Segment
{
    /**
     * @param list<float|null> $start premultiplied when $premultiplied
     * @param list<float|null> $end premultiplied when $premultiplied
     */
    private function __construct(
        private ColorSpace $space,
        private array $start,
        private array $end,
        private ?float $startAlpha,
        private ?float $endAlpha,
        private bool $premultiplied,
    ) {}

    public static function between(ColorValue $start, ColorValue $end, InterpolationMethod $method): self
    {
        $space = $method->space;
        $a = MissingComponents::convert($start, $space, $method->conversion);
        $b = MissingComponents::convert($end, $space, $method->conversion);
        $first = [];
        $second = [];

        foreach ($space->channels() as $index => $channel) {
            $from = $a->coordinates[$index] ?? $b->coordinates[$index];
            $to = $b->coordinates[$index] ?? $a->coordinates[$index];

            if ($channel->isHue() && $from !== null && $to !== null) {
                [$from, $to] = $method->hue->fixup(Angle::normalizeDegrees($from), Angle::normalizeDegrees($to));
            }

            $first[] = $from;
            $second[] = $to;
        }

        $startAlpha = $a->alpha ?? $b->alpha;
        $endAlpha = $b->alpha ?? $a->alpha;

        if ($startAlpha === $endAlpha) {
            return new self($space, $first, $second, $startAlpha, $endAlpha, false);
        }

        return new self($space, self::premultiply($space, $first, $startAlpha), self::premultiply($space, $second, $endAlpha), $startAlpha, $endAlpha, true);
    }

    /**
     * The color at $progress ∈ [0, 1] (0 = start, 1 = end), in the interpolation space. Hues are
     * normalised to [0, 360).
     *
     * @throws InvalidInterpolationException when the progress is not in [0, 1]
     */
    public function at(float $progress): ColorValue
    {
        if (!($progress >= 0.0 && $progress <= 1.0)) {
            throw new InvalidInterpolationException(\sprintf('The interpolation progress must be in [0, 1], %s given.', \var_export($progress, true)));
        }

        // Stays in [0, 1] without clamping: with a, b ∈ [0, 1], a·fl(1 − p) ≤ fl(1 − p) and b·p ≤ p
        // after rounding, and fl(fl(1 − p) + p) = 1, so rounding (monotonic) cannot exceed 1.
        $alpha = self::lerp($this->startAlpha, $this->endAlpha, $progress);
        $coordinates = [];

        foreach ($this->space->channels() as $index => $channel) {
            $value = self::lerp($this->start[$index], $this->end[$index], $progress);

            if ($value !== null && $channel->isHue()) {
                $value = Angle::normalizeDegrees($value);
            } elseif ($value !== null && $this->premultiplied && $alpha !== null && $alpha !== 0.0) {
                $value /= $alpha;
            }

            $coordinates[] = $value;
        }

        return new ColorValue($this->space, $coordinates, $alpha);
    }

    /**
     * @param list<float|null> $coordinates
     *
     * @return list<float|null>
     */
    private static function premultiply(ColorSpace $space, array $coordinates, ?float $alpha): array
    {
        if ($alpha === null) {
            return $coordinates;
        }

        $premultiplied = [];
        foreach ($space->channels() as $index => $channel) {
            $value = $coordinates[$index];
            $premultiplied[] = $channel->isHue() || $value === null ? $value : $value * $alpha;
        }

        return $premultiplied;
    }

    private static function lerp(?float $start, ?float $end, float $progress): ?float
    {
        // After the fill-in step both values are present or both are missing.
        if ($start === null || $end === null) {
            return null;
        }

        // Equal values stay exact (a·(1 − p) + a·p is not always a in binary64).
        return $start === $end ? $start : $start * (1.0 - $progress) + $end * $progress;
    }
}
