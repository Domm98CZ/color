<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Colorimetry\Contract;

/**
 * Transfer function between encoded (gamma-corrected) and linear-light RGB component values.
 *
 * Implementations must be defined over the whole real line (out-of-gamut values included),
 * typically by mirroring the curve for negative values as CSS Color 4 does.
 */
interface TransferFunction
{
    /** @return non-empty-string */
    public function id(): string;

    public function toLinear(float $encoded): float;

    public function toEncoded(float $linear): float;
}
