<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Contrast\Algorithm;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Compositing\Algorithm\SourceOver;
use Domm98CZ\Color\Compositing\LayerStack;
use Domm98CZ\Color\Contrast\Contract\ContrastAlgorithm;
use Domm98CZ\Color\Contrast\Exception\ColorOutsideSrgbException;
use Domm98CZ\Color\Contrast\Exception\MissingBackdropException;
use Domm98CZ\Color\Contrast\Result\ContrastMeasurement;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Provenance\Assumption;
use Domm98CZ\Color\Provenance\Diagnostics;
use Domm98CZ\Color\Provenance\SourceKind;
use Domm98CZ\Color\Provenance\SourceReference;
use Domm98CZ\Color\Space\ColorSpaces;

/**
 * WCAG 2 contrast ratio (L1 + 0.05) / (L2 + 0.05), L1 the relative luminance of the lighter color.
 *
 * Relative luminance, as WCAG defines it for sRGB: each channel linearised with the sRGB curve
 * (threshold 0.04045, or 0.03928 in WCAG 2.0), then L = 0.2126·R + 0.7152·G + 0.0722·B.
 *
 * - The background must be opaque (a Color with alpha 1, or a LayerStack that composites to one);
 *   otherwise {@see MissingBackdropException}. No white page is assumed.
 * - A translucent foreground is composited over the background (source-over in sRGB), because
 *   that is the color that is rendered.
 * - Colors must be in the sRGB gamut (within 1e-6, which is clamped); otherwise
 *   {@see ColorOutsideSrgbException}.
 * - The value is not rounded.
 */
final readonly class Wcag2ContrastRatio implements ContrastAlgorithm
{
    /** Same as Color::DEFAULT_GAMUT_TOLERANCE. */
    private const float GAMUT_TOLERANCE = 1e-6;

    public function __construct(private Wcag2LuminanceThreshold $threshold = Wcag2LuminanceThreshold::Current) {}

    public function id(): AlgorithmId
    {
        $source = $this->threshold === Wcag2LuminanceThreshold::Current
            ? new SourceReference('Web Content Accessibility Guidelines (WCAG) 2.2', SourceKind::Standard, 'W3C Recommendation, 12 December 2024', 'definitions: contrast ratio, relative luminance', 'https://www.w3.org/TR/WCAG22/')
            : new SourceReference('Web Content Accessibility Guidelines (WCAG) 2.0', SourceKind::Standard, 'W3C Recommendation, 11 December 2008', 'definitions: contrast ratio, relative luminance', 'https://www.w3.org/TR/WCAG20/');

        return new AlgorithmId('wcag2-contrast-ratio', 1, [$source], 'threshold=' . $this->threshold->value);
    }

    public function measure(Color $foreground, Color|LayerStack $background): ContrastMeasurement
    {
        $backgroundComposition = null;

        if ($background instanceof LayerStack) {
            $backgroundComposition = $background->composite();
            $background = $backgroundComposition->color;
        }

        if ($background->alpha() !== 1.0) {
            throw MissingBackdropException::forAlpha($background->alpha());
        }

        $diagnostics = Diagnostics::none();
        $foregroundComposition = null;

        if ($foreground->alpha() !== 1.0) {
            $foregroundComposition = LayerStack::of($foreground, $background)->composite(new SourceOver());
            $foreground = $foregroundComposition->color;
            $diagnostics = $diagnostics->withAssumption(new Assumption(
                'translucent-foreground-composited',
                'The translucent foreground was composited over the background (source-over in sRGB) and the resulting opaque color was measured.',
            ));
        }

        $foregroundLuminance = $this->relativeLuminance($foreground);
        $backgroundLuminance = $this->relativeLuminance($background);
        $lighter = \max($foregroundLuminance, $backgroundLuminance);
        $darker = \min($foregroundLuminance, $backgroundLuminance);

        return new ContrastMeasurement(
            ($lighter + 0.05) / ($darker + 0.05),
            $this->id(),
            $foreground,
            $background,
            $backgroundComposition,
            $foregroundComposition,
            $diagnostics,
        );
    }

    /**
     * WCAG relative luminance of a color in the sRGB gamut (alpha is not used).
     *
     * @throws ColorOutsideSrgbException
     */
    public function relativeLuminance(Color $color): float
    {
        $srgb = $color->to(ColorSpaces::srgb())->coordinates();
        $linear = [];

        foreach ($srgb as $value) {
            $value ??= 0.0;

            if ($value < -self::GAMUT_TOLERANCE || $value > 1.0 + self::GAMUT_TOLERANCE) {
                throw new ColorOutsideSrgbException($color);
            }

            $value = \max(0.0, \min(1.0, $value));
            $linear[] = $value <= $this->threshold->threshold() ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
