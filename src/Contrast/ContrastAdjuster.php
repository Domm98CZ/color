<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Contrast;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Compositing\LayerStack;
use Domm98CZ\Color\Contrast\Contract\ContrastAlgorithm;
use Domm98CZ\Color\Contrast\Exception\ColorOutsideSrgbException;
use Domm98CZ\Color\Contrast\Exception\MissingBackdropException;
use Domm98CZ\Color\Contrast\Internal\Candidate;
use Domm98CZ\Color\Contrast\Result\ContrastAdjustment;
use Domm98CZ\Color\Contrast\Result\ContrastMeasurement;
use Domm98CZ\Color\Gamut\Algorithm\CssColor4BinarySearch;
use Domm98CZ\Color\Gamut\Contract\GamutMapper;
use Domm98CZ\Color\Math\Exception\NonFiniteValueException;
use Domm98CZ\Color\Math\Numeric;
use Domm98CZ\Color\Operation\Adjuster;
use Domm98CZ\Color\Operation\LightnessModel;
use Domm98CZ\Color\Operation\Quantizer;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Provenance\SourceKind;
use Domm98CZ\Color\Provenance\SourceReference;
use Domm98CZ\Color\Provenance\Warning;
use Domm98CZ\Color\Provenance\WarningCode;
use Domm98CZ\Color\Space\ColorSpaces;

/**
 * Moves a color along the lightness axis of a model (hue and chroma kept) until its contrast
 * against a fixed color reaches a target, and returns the passing candidate with the smallest
 * lightness change (towards black on a tie). Every candidate is mapped into sRGB and quantised,
 * so the contrast reported is the contrast of the color as written by `toHex()`.
 *
 * - The input is tried first; if it passes it is returned fitted, with change 0.
 * - Each direction (towards black, towards white) is decided by its extreme: if even the extreme
 *   fails, the direction cannot reach the target; otherwise a bisection on the model lightness
 *   finds the boundary to {@see PRECISION} of the model's range.
 * - An unreachable target is a result, not an error: the extreme with the higher contrast is
 *   returned with `reachable = false` and a `contrast-target-unreachable` warning.
 * - The fixed color follows the rules of the contrast algorithm (WCAG 2: an opaque background or
 *   a LayerStack, colors inside sRGB) and is never mapped.
 * - Gamut mapping may move the lightness within one JND and quantisation rounds, so the passing
 *   region is not strictly monotonic: the bisection finds a boundary, not necessarily the one
 *   nearest to the input. Only candidates measured as passing are kept, so the result passes
 *   whenever `reachable` is true.
 */
final readonly class ContrastAdjuster
{
    /** The bisection stops when the interval is this fraction of the model's lightness range. */
    public const float PRECISION = 1e-6;

    /**
     * @param GamutMapper $mapper every candidate is mapped into sRGB with it (the search leaves the gamut by construction)
     * @param Quantizer|null $quantizer rounding of every candidate (default 8-bit sRGB); null evaluates unrounded colors
     */
    public function __construct(
        private ContrastAlgorithm $algorithm,
        private LightnessModel $lightness,
        private GamutMapper $mapper = new CssColor4BinarySearch(),
        private ?Quantizer $quantizer = new Quantizer(),
    ) {}

    public function id(): AlgorithmId
    {
        return new AlgorithmId(
            'contrast-lightness-search',
            1,
            [new SourceReference('domm98cz/color', SourceKind::Library, null, 'ContrastAdjuster: bisection of the model lightness to a contrast target')],
            \sprintf('lightness=%s,bits=%s', $this->lightness->value, $this->quantizer === null ? 'none' : (string) $this->quantizer->bits),
        );
    }

    /**
     * @throws NonFiniteValueException when the target is NaN or infinite
     * @throws MissingBackdropException when the background is translucent (as the algorithm's measure())
     * @throws ColorOutsideSrgbException when the background is outside sRGB (WCAG 2)
     */
    public function adjustForeground(Color $foreground, Color|LayerStack $background, float $target): ContrastAdjustment
    {
        return $this->adjust($foreground, $target, fn(Color $candidate): ContrastMeasurement => $this->algorithm->measure($candidate, $background));
    }

    /**
     * @throws NonFiniteValueException when the target is NaN or infinite
     * @throws MissingBackdropException when the background to adjust is translucent (nothing is behind it)
     * @throws ColorOutsideSrgbException when the foreground is outside sRGB (WCAG 2)
     */
    public function adjustBackground(Color $background, Color $foreground, float $target): ContrastAdjustment
    {
        return $this->adjust($background, $target, fn(Color $candidate): ContrastMeasurement => $this->algorithm->measure($foreground, $candidate));
    }

    /**
     * @param \Closure(Color): ContrastMeasurement $measure the candidate in its role against the fixed color
     */
    private function adjust(Color $original, float $target, \Closure $measure): ContrastAdjustment
    {
        Numeric::assertFinite($target, 'The contrast target');

        $maximum = $this->lightness->maximum();
        $origin = \max(0.0, \min($maximum, $original->to($this->lightness->space())->coordinate($this->lightness->channel()) ?? 0.0));
        $start = $this->evaluate($original, $origin, $measure);

        if ($start->passes($target)) {
            return $this->result($original, $target, $start, 0.0, true);
        }

        $darker = $this->direction($original, $origin, 0.0, $target, $measure);
        $lighter = $this->direction($original, $origin, $maximum, $target, $measure);

        if ($darker->passes($target) || $lighter->passes($target)) {
            $best = match (true) {
                !$lighter->passes($target) => $darker,
                !$darker->passes($target) => $lighter,
                default => \abs($lighter->lightness - $origin) < \abs($darker->lightness - $origin) ? $lighter : $darker,
            };

            return $this->result($original, $target, $best, $best->lightness - $origin, true);
        }

        $best = $lighter->measurement->value > $darker->measurement->value ? $lighter : $darker;

        return $this->result($original, $target, $best, $best->lightness - $origin, false);
    }

    /**
     * The boundary candidate between $origin (failing) and $extreme when the extreme passes, found
     * by bisection; the extreme itself when even it fails.
     *
     * @param \Closure(Color): ContrastMeasurement $measure
     */
    private function direction(Color $original, float $origin, float $extreme, float $target, \Closure $measure): Candidate
    {
        $candidate = $this->evaluate($this->candidate($original, $extreme), $extreme, $measure);

        if (!$candidate->passes($target)) {
            return $candidate;
        }

        $failing = $origin;
        $passing = $extreme;

        while (\abs($passing - $failing) > self::PRECISION * $this->lightness->maximum()) {
            $middle = ($failing + $passing) / 2.0;
            $trial = $this->evaluate($this->candidate($original, $middle), $middle, $measure);

            if ($trial->passes($target)) {
                [$passing, $candidate] = [$middle, $trial];
            } else {
                $failing = $middle;
            }
        }

        return $candidate;
    }

    private function candidate(Color $original, float $lightness): Color
    {
        return (new Adjuster())->withLightness($original, $lightness, $this->lightness);
    }

    /**
     * Maps into sRGB (the space the contrast is measured in), quantises and measures.
     *
     * @param \Closure(Color): ContrastMeasurement $measure
     */
    private function evaluate(Color $color, float $lightness, \Closure $measure): Candidate
    {
        $mapping = $this->mapper->map($color, ColorSpaces::srgb());
        $fitted = $this->quantizer === null ? $mapping->color : $this->quantizer->quantize($mapping->color)->color;

        return new Candidate($fitted, $mapping, $measure($fitted), $lightness);
    }

    private function result(Color $original, float $target, Candidate $best, float $change, bool $reachable): ContrastAdjustment
    {
        $diagnostics = $best->measurement->diagnostics;

        if (!$reachable) {
            $diagnostics = $diagnostics->withWarning(new Warning(
                WarningCode::ContrastTargetUnreachable,
                \sprintf('No lightness of the adjusted color reaches the contrast target %s against the fixed color; the best candidate reaches %s.', \var_export($target, true), \var_export($best->measurement->value, true)),
                ['target' => $target, 'contrast' => $best->measurement->value],
            ));
        }

        return new ContrastAdjustment($best->color, $original, $best->measurement, $target, $reachable, $this->lightness, $change, $best->mapping, $this->id(), $diagnostics);
    }
}
