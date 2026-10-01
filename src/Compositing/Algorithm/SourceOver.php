<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Compositing\Algorithm;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Compositing\Contract\CompositeOperator;
use Domm98CZ\Color\Compositing\Exception\InvalidCompositingSpaceException;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Provenance\SourceKind;
use Domm98CZ\Color\Provenance\SourceReference;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Porter–Duff source-over, "simple alpha compositing" (W3C Compositing and Blending Level 1,
 * § 5.1 and § 9.1.4; required by CSS Color 4 § 10.13):
 *
 *     αo = αs + αb·(1 − αs),   Co = (αs·Cs + αb·Cb·(1 − αs)) / αo
 *
 * computed per channel in the compositing space (default sRGB, the space browsers composite in).
 * Missing components and a missing alpha count as 0 (CSS Color 4 § 4.4). When αo = 0 the result
 * is transparent black. Values are not clipped.
 */
final readonly class SourceOver implements CompositeOperator
{
    private ColorSpace $space;

    public function __construct(?ColorSpace $space = null)
    {
        $space ??= ColorSpaces::srgb();

        foreach ($space->channels() as $channel) {
            if ($channel->isHue()) {
                throw new InvalidCompositingSpaceException(\sprintf('The space "%s" has a hue channel and cannot be used for compositing.', $space->id()));
            }
        }

        $this->space = $space;
    }

    public function id(): AlgorithmId
    {
        return new AlgorithmId('source-over', 1, [
            new SourceReference('Compositing and Blending Level 1', SourceKind::Specification, 'W3C Candidate Recommendation Draft, 21 March 2024', '§ 5.1 Simple alpha compositing, § 9.1.4 Source Over', 'https://www.w3.org/TR/compositing-1/'),
        ], 'space=' . $this->space->id());
    }

    public function space(): ColorSpace
    {
        return $this->space;
    }

    public function composite(Color $source, Color $backdrop): Color
    {
        $alphaSource = $source->alpha();
        $alphaBackdrop = $backdrop->alpha();
        // With an opaque layer this is exactly 1: 1 + αb·0 = 1, and αs + (1 − αs) = 1 in binary64.
        $alpha = $alphaSource + $alphaBackdrop * (1.0 - $alphaSource);

        if ($alpha === 0.0) {
            return Color::of($this->space, \array_fill(0, \count($this->space->channels()), 0.0), 0.0);
        }

        $sourceCoordinates = $source->to($this->space)->coordinates();
        $backdropCoordinates = $backdrop->to($this->space)->coordinates();

        $result = [];
        foreach ($sourceCoordinates as $index => $value) {
            $result[] = (($value ?? 0.0) * $alphaSource + ($backdropCoordinates[$index] ?? 0.0) * $alphaBackdrop * (1.0 - $alphaSource)) / $alpha;
        }

        return Color::of($this->space, $result, \min(1.0, $alpha));
    }
}
