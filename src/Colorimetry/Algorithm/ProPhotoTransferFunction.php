<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Colorimetry\Algorithm;

use Domm98CZ\Color\Colorimetry\Contract\TransferFunction;

/**
 * ProPhoto RGB (ROMM RGB, ISO 22028-2) transfer function: gamma 1.8 with a linear segment near zero,
 * extended to negative values by reflection, exactly as CSS Color 4 (lin_ProPhoto / gam_ProPhoto).
 */
final class ProPhotoTransferFunction implements TransferFunction
{
    /** Encoded breakpoint 16/512 (lin_ProPhoto, Et2). */
    private const float ENCODED_BREAKPOINT = 16 / 512;

    /** Linear breakpoint 1/512 (gam_ProPhoto, Et). */
    private const float LINEAR_BREAKPOINT = 1 / 512;

    public function id(): string
    {
        return 'prophoto';
    }

    public function toLinear(float $encoded): float
    {
        $magnitude = \abs($encoded);

        if ($magnitude <= self::ENCODED_BREAKPOINT) {
            return $encoded / 16.0;
        }

        $linear = $magnitude ** 1.8;

        return $encoded < 0.0 ? -$linear : $linear;
    }

    public function toEncoded(float $linear): float
    {
        $magnitude = \abs($linear);

        if ($magnitude >= self::LINEAR_BREAKPOINT) {
            $encoded = $magnitude ** (1.0 / 1.8);

            return $linear < 0.0 ? -$encoded : $encoded;
        }

        return 16.0 * $linear;
    }
}
