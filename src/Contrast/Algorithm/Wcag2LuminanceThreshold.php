<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Contrast\Algorithm;

/**
 * The encoded-value threshold of the linear segment in the WCAG relative-luminance definition.
 *
 * WCAG 2.1 (since May 2021) and 2.2 use 0.04045, as sRGB does; WCAG 2.0 (2008) uses 0.03928.
 * WCAG notes that the change "has no practical effect on the calculations".
 */
enum Wcag2LuminanceThreshold: string
{
    case Current = '0.04045';
    case Wcag20 = '0.03928';

    /**
     * The threshold as a number (the case value is its exact decimal text).
     */
    public function threshold(): float
    {
        return (float) $this->value;
    }
}
