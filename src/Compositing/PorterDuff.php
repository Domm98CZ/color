<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Compositing;

/**
 * The Porter–Duff compositing operators (W3C Compositing and Blending Level 1, § 9.1; Porter and
 * Duff 1984). Each operator is a pair of fractions: Fa of the source and Fb of the backdrop, so
 * that αo = αs·Fa + αb·Fb and co = αs·Fa·Cs + αb·Fb·Cb. The value is the CSS/Canvas keyword.
 */
enum PorterDuff: string
{
    case Clear = 'clear';
    case Copy = 'copy';
    case Destination = 'destination';
    case SourceOver = 'source-over';
    case DestinationOver = 'destination-over';
    case SourceIn = 'source-in';
    case DestinationIn = 'destination-in';
    case SourceOut = 'source-out';
    case DestinationOut = 'destination-out';
    case SourceAtop = 'source-atop';
    case DestinationAtop = 'destination-atop';
    case Xor = 'xor';
    /** "plus" in the Porter–Duff paper: the sum of both; the alpha is limited to 1. */
    case Lighter = 'lighter';

    /**
     * Fa: the fraction of the source that contributes.
     */
    public function sourceFraction(float $sourceAlpha, float $backdropAlpha): float
    {
        return match ($this) {
            self::Clear, self::Destination, self::DestinationIn, self::DestinationOut => 0.0,
            self::Copy, self::SourceOver, self::Lighter => 1.0,
            self::DestinationOver, self::SourceOut, self::DestinationAtop, self::Xor => 1.0 - $backdropAlpha,
            self::SourceIn, self::SourceAtop => $backdropAlpha,
        };
    }

    /**
     * Fb: the fraction of the backdrop that contributes.
     */
    public function backdropFraction(float $sourceAlpha, float $backdropAlpha): float
    {
        return match ($this) {
            self::Clear, self::Copy, self::SourceIn, self::SourceOut => 0.0,
            self::Destination, self::DestinationOver, self::Lighter => 1.0,
            self::SourceOver, self::DestinationOut, self::SourceAtop, self::Xor => 1.0 - $sourceAlpha,
            self::DestinationIn, self::DestinationAtop => $sourceAlpha,
        };
    }
}
