<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Operation;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Interpolation\HueInterpolation;
use Domm98CZ\Color\Interpolation\InterpolationMethod;
use Domm98CZ\Color\Interpolation\Segment;
use Domm98CZ\Color\Notation\CssFormat;
use Domm98CZ\Color\Operation\Exception\InvalidOperationException;
use Domm98CZ\Color\Operation\Exception\UnsupportedOperationSpaceException;
use Domm98CZ\Color\Operation\Internal\Values;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * A gradient: color stops at positions in [0, 1], interpolated as CSS gradients are
 * (CSS Images 4, WD 30 September 2025, § 3.5; CSS Color 4 § 13).
 *
 * - Between two stops the color is interpolated in the gradient's space with premultiplied alpha,
 *   missing components and the hue method ({@see Segment}). The default space is OKLab, the CSS
 *   default. (CSS interpolates between legacy sRGB colors in sRGB when no space is given; this
 *   library has no legacy colors and always uses the explicit or the default space.)
 * - After the last stop the color is that of the last stop ({@see of()} puts the first stop at 0;
 *   CSS: before the first stop, the first stop's color).
 * - Stops at the same position make a hard edge; at that position the later stop applies.
 * - Colors returned by {@see at()} and {@see sample()} are in the interpolation space.
 *
 * Immutable: {@see withStop()} and {@see in()} return new gradients.
 */
final readonly class Gradient
{
    /** Interpolation spaces CSS can name in `<color-interpolation-method>` (CSS Color 4 § 13.2). */
    private const array CSS_SPACES = [
        'srgb', 'srgb-linear', 'display-p3', 'display-p3-linear', 'a98-rgb', 'prophoto-rgb', 'rec2020',
        'lab', 'oklab', 'xyz-d50', 'xyz-d65', 'hsl', 'hwb', 'lch', 'oklch',
    ];

    /**
     * @param non-empty-list<GradientStop> $stops sorted by position (stable)
     */
    private function __construct(
        private array $stops,
        public InterpolationMethod $method,
    ) {}

    /**
     * Stops evenly spaced from 0 to 1, interpolated in OKLab.
     *
     * @throws InvalidOperationException when fewer than two colors are given
     */
    public static function of(Color ...$colors): self
    {
        $colors = \array_values($colors);
        $count = \count($colors);

        if ($count < 2) {
            throw new InvalidOperationException(\sprintf('A gradient needs at least two colors, %d given.', $count));
        }

        $stops = [];
        foreach ($colors as $index => $color) {
            $stops[] = new GradientStop($color, $index / ($count - 1));
        }

        return new self($stops, InterpolationMethod::oklab());
    }

    /**
     * Adds a stop at $at ∈ [0, 1], after the existing stops at the same position.
     *
     * @throws InvalidOperationException when the position is not in [0, 1]
     */
    public function withStop(Color $color, float $at): self
    {
        $stop = new GradientStop($color, Values::fraction($at, 'A gradient stop position'));
        $stops = [];
        $inserted = false;

        foreach ($this->stops as $existing) {
            if (!$inserted && $existing->position > $at) {
                $stops[] = $stop;
                $inserted = true;
            }
            $stops[] = $existing;
        }

        if (!$inserted) {
            $stops[] = $stop;
        }

        return new self($stops, $this->method);
    }

    public function in(ColorSpace $space, HueInterpolation $hue = HueInterpolation::Shorter): self
    {
        return new self($this->stops, new InterpolationMethod($space, $hue));
    }

    /**
     * @return non-empty-list<GradientStop>
     */
    public function stops(): array
    {
        return $this->stops;
    }

    /**
     * The color at $position ∈ [0, 1].
     *
     * @throws InvalidOperationException when the position is not in [0, 1]
     */
    public function at(float $position): Color
    {
        Values::fraction($position, 'A gradient position');
        $last = \count($this->stops) - 1;

        if ($position >= $this->stops[$last]->position) {
            return Values::color($this->segment($last - 1)->at(1.0));
        }

        // The last stop at or before the position (the first stop is at 0, so there is one);
        // after a hard edge, the later stop applies.
        $index = $last;
        while ($this->stops[$index]->position > $position) {
            $index--;
        }

        $start = $this->stops[$index]->position;
        $end = $this->stops[$index + 1]->position;

        // $end > $position ≥ $start here, so the progress is in [0, 1).
        return Values::color($this->segment($index)->at(($position - $start) / ($end - $start)));
    }

    /**
     * $count colors at evenly spaced positions from 0 to 1 (both included).
     *
     * @return list<Color>
     *
     * @throws InvalidOperationException when $count < 2
     */
    public function sample(int $count): array
    {
        if ($count < 2) {
            throw new InvalidOperationException(\sprintf('A gradient sample needs at least two colors, %d requested.', $count));
        }

        $colors = [];
        for ($index = 0; $index < $count; $index++) {
            $colors[] = $this->at($index / ($count - 1));
        }

        return $colors;
    }

    /**
     * CSS `linear-gradient()` (CSS Images 4 § 3.1) with an explicit `<color-interpolation-method>`,
     * e.g. `linear-gradient(in oklch longer hue 90deg, oklch(…) 0%, rgb(…) 70%, …)`. The hue
     * method is written only when it is not `shorter`, the CSS default. Colors are written by
     * `Color::toCss()` in their own spaces; nothing is gamut mapped.
     *
     * @param float|null $angle direction in degrees; null leaves the CSS default (to bottom)
     *
     * @throws UnsupportedOperationSpaceException when CSS cannot name the interpolation space (e.g. hsv)
     * @throws InvalidOperationException when the angle is not finite
     */
    public function toCss(?float $angle = null, ?CssFormat $format = null): string
    {
        $format ??= new CssFormat();
        $space = $this->method->space->id();

        if (!\in_array($space, self::CSS_SPACES, true)) {
            throw new UnsupportedOperationSpaceException(\sprintf('CSS has no <color-interpolation-method> for the space "%s"; use one of: %s.', $space, \implode(', ', self::CSS_SPACES)));
        }

        $prelude = 'in ' . $space;
        if ($this->method->isPolar() && $this->method->hue !== HueInterpolation::Shorter) {
            $prelude .= ' ' . $this->method->hue->value . ' hue';
        }

        if ($angle !== null) {
            $prelude .= ' ' . $format->number(Values::finite($angle, 'The gradient angle')) . 'deg';
        }

        $stops = \array_map(
            static fn(GradientStop $stop): string => $stop->color->toCss($format) . ' ' . $format->number($stop->position * 100) . '%',
            $this->stops,
        );

        return \sprintf('linear-gradient(%s, %s)', $prelude, \implode(', ', $stops));
    }

    private function segment(int $index): Segment
    {
        return Segment::between(Values::of($this->stops[$index]->color), Values::of($this->stops[$index + 1]->color), $this->method);
    }
}
