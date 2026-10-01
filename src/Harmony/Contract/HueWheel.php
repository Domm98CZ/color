<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Harmony\Contract;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Provenance\AlgorithmId;

/**
 * A color wheel: a way to place colors on a hue circle and to move a color around it.
 *
 * There is no single "color wheel": the HSL (RGB) wheel, the perceptual OKLCh wheel and the painter's
 * RYB wheel put the complement of a color in different places. A wheel is therefore always chosen
 * explicitly.
 */
interface HueWheel
{
    public function id(): AlgorithmId;

    /**
     * The angle of $color on this wheel in [0, 360), or null when the color has no hue (achromatic).
     */
    public function hueOf(Color $color): ?float;

    /**
     * $template moved to the wheel angle $angle; everything the wheel does not treat as hue stays.
     * The result is in the template's space and is never clipped.
     */
    public function colorAt(float $angle, Color $template): Color;
}
