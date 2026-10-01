<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Gamut\Internal;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Difference\DeltaE;
use Domm98CZ\Color\Gamut\Result\GamutMappingResult;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Provenance\SourceKind;
use Domm98CZ\Color\Provenance\SourceReference;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * @internal steps shared by the gamut mappers
 */
final class Destination
{
    private function __construct() {}

    /**
     * Converts $color to $destination with every component of the destination's gamut space
     * clamped to its reference range (CSS Color 4 § 14.2.2 "clip"). The mappers also use it for
     * colors already in gamut (within Color::DEFAULT_GAMUT_TOLERANCE), so that every result is
     * strictly in gamut and mapping is idempotent.
     */
    public static function clip(Color $color, ColorSpace $destination): Color
    {
        $gamut = $destination->gamutSpace() ?? $destination;
        $coordinates = $color->to($gamut)->coordinates();

        foreach ($gamut->channels() as $index => $channel) {
            $value = $coordinates[$index];
            $coordinates[$index] = $value === null ? null : \max($channel->referenceMin, \min($channel->referenceMax, $value));
        }

        return Color::of($gamut, $coordinates, $color->isAlphaMissing() ? null : $color->alpha())->to($destination);
    }

    /**
     * White (L ≥ 1) or black (L ≤ 0) in the destination, keeping the alpha (CSS Color 4 § 14.2).
     */
    public static function extreme(Color $origin, float $lightness, ColorSpace $destination): Color
    {
        return Color::of(ColorSpaces::oklab(), [$lightness >= 1.0 ? 1.0 : 0.0, 0.0, 0.0], $origin->isAlphaMissing() ? null : $origin->alpha())->to($destination);
    }

    public static function result(Color $mapped, Color $original, ColorSpace $destination, AlgorithmId $method): GamutMappingResult
    {
        return new GamutMappingResult(
            $mapped,
            $original,
            $destination,
            $original->isInGamut($destination),
            $method,
            DeltaE::ok()->difference($original, $mapped),
        );
    }

    /**
     * @param non-empty-string $section
     */
    public static function cssColor4(string $section): SourceReference
    {
        return new SourceReference('CSS Color Module Level 4', SourceKind::Specification, 'W3C Candidate Recommendation Draft, 26 September 2026', $section, 'https://www.w3.org/TR/css-color-4/');
    }
}
