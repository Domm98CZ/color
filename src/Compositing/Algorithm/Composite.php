<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Compositing\Algorithm;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Compositing\BlendMode;
use Domm98CZ\Color\Compositing\Contract\CompositeOperator;
use Domm98CZ\Color\Compositing\Exception\InvalidCompositingSpaceException;
use Domm98CZ\Color\Compositing\PorterDuff;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Provenance\SourceKind;
use Domm98CZ\Color\Provenance\SourceReference;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\ComponentCategory;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * The general formula for compositing and blending (W3C Compositing and Blending Level 1, § 6):
 *
 *     Cs′ = (1 − αb)·Cs + αb·B(Cb, Cs)          blend in place ({@see BlendMode})
 *     αo  = αs·Fa + αb·Fb                        ({@see PorterDuff})
 *     Co  = (αs·Fa·Cs′ + αb·Fb·Cb) / αo
 *
 * per channel in the compositing space: sRGB by default (as browsers composite), or a linear-light
 * space such as `srgb-linear` for physically additive light. Missing components and a missing alpha
 * count as 0 (CSS Color 4 § 4.4). When αo = 0 the result is transparent black.
 *
 * `lighter` (Porter–Duff plus) saturates: αo and the premultiplied sum co are limited to 1
 * (§ 9.1: 0 ≤ co ≤ 1; canvas behaves so). Otherwise nothing is clipped except the result of B,
 * so out-of-gamut inputs give out-of-gamut results.
 *
 * Blend modes other than `normal` need an RGB-like space (channels in the Reds, Greens, Blues
 * categories, e.g. any RGB space, linear or not).
 */
final readonly class Composite implements CompositeOperator
{
    private ColorSpace $space;

    public function __construct(
        public PorterDuff $operator = PorterDuff::SourceOver,
        public BlendMode $blend = BlendMode::Normal,
        ?ColorSpace $space = null,
    ) {
        $space ??= ColorSpaces::srgb();

        foreach ($space->channels() as $channel) {
            if ($channel->isHue()) {
                throw new InvalidCompositingSpaceException(\sprintf('The space "%s" has a hue channel and cannot be used for compositing.', $space->id()));
            }
        }

        $categories = \array_map(static fn($channel): ?ComponentCategory => $channel->category, $space->channels());
        if ($blend !== BlendMode::Normal && $categories !== [ComponentCategory::Reds, ComponentCategory::Greens, ComponentCategory::Blues]) {
            throw new InvalidCompositingSpaceException(\sprintf('The blend mode "%s" needs an RGB space; "%s" is not one.', $blend->value, $space->id()));
        }

        $this->space = $space;
    }

    public function id(): AlgorithmId
    {
        return new AlgorithmId('composite', 1, [
            new SourceReference('Compositing and Blending Level 1', SourceKind::Specification, 'W3C Candidate Recommendation Draft, 21 March 2024', '§ 6 General formula, § 9.1 Porter Duff operators, § 10 Blending', 'https://www.w3.org/TR/compositing-1/'),
        ], \sprintf('operator=%s,blend=%s,space=%s', $this->operator->value, $this->blend->value, $this->space->id()));
    }

    public function space(): ColorSpace
    {
        return $this->space;
    }

    public function composite(Color $source, Color $backdrop): Color
    {
        $alphaSource = $source->alpha();
        $alphaBackdrop = $backdrop->alpha();
        $fa = $this->operator->sourceFraction($alphaSource, $alphaBackdrop);
        $fb = $this->operator->backdropFraction($alphaSource, $alphaBackdrop);
        $alpha = \min(1.0, $alphaSource * $fa + $alphaBackdrop * $fb);

        if ($alpha === 0.0) {
            return Color::of($this->space, \array_fill(0, \count($this->space->channels()), 0.0), 0.0);
        }

        $cs = \array_map(static fn(?float $v): float => $v ?? 0.0, $source->to($this->space)->coordinates());
        $cb = \array_map(static fn(?float $v): float => $v ?? 0.0, $backdrop->to($this->space)->coordinates());

        if ($this->blend !== BlendMode::Normal) {
            // The constructor guarantees three channels here.
            $mixed = $this->blend->blend([$cb[0], $cb[1], $cb[2]], [$cs[0], $cs[1], $cs[2]]);
            foreach ($cs as $index => $value) {
                $cs[$index] = (1.0 - $alphaBackdrop) * $value + $alphaBackdrop * $mixed[$index];
            }
        }

        $result = [];
        foreach ($cs as $index => $value) {
            $premultiplied = $alphaSource * $fa * $value + $alphaBackdrop * $fb * $cb[$index];
            $result[] = ($this->operator === PorterDuff::Lighter ? \min(1.0, $premultiplied) : $premultiplied) / $alpha;
        }

        return Color::of($this->space, $result, $alpha);
    }
}
