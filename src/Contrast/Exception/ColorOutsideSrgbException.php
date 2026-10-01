<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Contrast\Exception;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Exception\DomainException;

/**
 * The WCAG 2 contrast ratio is defined for sRGB colors. A color outside the sRGB gamut has no
 * defined relative luminance; it is not clipped silently. Map it into sRGB explicitly first.
 */
final class ColorOutsideSrgbException extends DomainException
{
    public function __construct(private readonly Color $color)
    {
        parent::__construct(\sprintf('The color %s is outside the sRGB gamut; WCAG 2 relative luminance is defined for sRGB colors only. Map it into sRGB explicitly first.', $color->toCss()));
    }

    public function getColor(): Color
    {
        return $this->color;
    }
}
