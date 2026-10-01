<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Colorimetry\Algorithm;

use Domm98CZ\Color\Colorimetry\Contract\TransferFunction;

/**
 * sRGB transfer function (IEC 61966-2-1), extended to negative values by reflection as in
 * CSS Color 4 (lin_sRGB / gam_sRGB). Also used by Display P3.
 */
final class SrgbTransferFunction implements TransferFunction
{
    public function id(): string
    {
        return 'srgb';
    }

    public function toLinear(float $encoded): float
    {
        $magnitude = \abs($encoded);

        if ($magnitude <= 0.04045) {
            return $encoded / 12.92;
        }

        $linear = (($magnitude + 0.055) / 1.055) ** 2.4;

        return $encoded < 0.0 ? -$linear : $linear;
    }

    public function toEncoded(float $linear): float
    {
        $magnitude = \abs($linear);

        if ($magnitude > 0.0031308) {
            $encoded = 1.055 * $magnitude ** (1.0 / 2.4) - 0.055;

            return $linear < 0.0 ? -$encoded : $encoded;
        }

        return 12.92 * $linear;
    }
}
