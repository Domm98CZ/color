<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Colorimetry\Algorithm;

use Domm98CZ\Color\Colorimetry\Contract\TransferFunction;
use Domm98CZ\Color\Colorimetry\Exception\InvalidColorimetricDataException;
use Domm98CZ\Color\Math\Numeric;

/**
 * Pure power-law transfer function, mirrored for negative values:
 * linear = sign(v)·|v|^γ, encoded = sign(v)·|v|^(1/γ).
 *
 * Used by a98-rgb (γ = 563/256) and rec2020 (γ = 2.4, the BT.1886 EOTF with zero black lift and unit
 * gain, as CSS Color 4 CRD 2026-09-26 defines it).
 */
final readonly class GammaTransferFunction implements TransferFunction
{
    public function __construct(public float $gamma)
    {
        if (!\is_finite($gamma) || $gamma <= 0.0) {
            throw new InvalidColorimetricDataException(\sprintf('A gamma must be a finite number > 0, %s given.', \var_export($gamma, true)));
        }
    }

    public function id(): string
    {
        return 'gamma-' . \rtrim(\rtrim(\sprintf('%.10F', $this->gamma), '0'), '.');
    }

    public function toLinear(float $encoded): float
    {
        return Numeric::signedPow($encoded, $this->gamma);
    }

    public function toEncoded(float $linear): float
    {
        return Numeric::signedPow($linear, 1.0 / $this->gamma);
    }
}
