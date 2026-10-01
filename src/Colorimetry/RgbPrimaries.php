<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Colorimetry;

use Domm98CZ\Color\Math\Matrix3;

/**
 * Chromaticities of the red, green and blue primaries of an RGB color space.
 */
final readonly class RgbPrimaries
{
    public function __construct(
        public Chromaticity $red,
        public Chromaticity $green,
        public Chromaticity $blue,
    ) {}

    /**
     * Normalised primary matrix (linear RGB → XYZ with the white at Y = 1), SMPTE RP 177-1993:
     *
     *   P = [ x/y of each primary ; 1 ; z/y of each primary ] (columns R, G, B)
     *   S = P⁻¹ · W
     *   M = P · diag(S)
     *
     * @throws \Domm98CZ\Color\Math\Exception\SingularMatrixException when the primaries are collinear
     */
    public function toXyzMatrix(WhitePoint $white): Matrix3
    {
        $r = $this->red->toXyz(1.0);
        $g = $this->green->toXyz(1.0);
        $b = $this->blue->toXyz(1.0);

        $primaries = Matrix3::fromRows([
            [$r->x, $g->x, $b->x],
            [$r->y, $g->y, $b->y],
            [$r->z, $g->z, $b->z],
        ]);

        $scale = $primaries->inverse()->transform($white->xyz);

        return $primaries->multiply(Matrix3::diagonal($scale->x, $scale->y, $scale->z));
    }
}
