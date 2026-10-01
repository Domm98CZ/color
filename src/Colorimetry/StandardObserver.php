<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Colorimetry;

/**
 * CIE standard colorimetric observer (CIE 15:2018). All digital color spaces in this library are
 * defined for the CIE 1931 2° observer.
 */
enum StandardObserver: string
{
    case Cie1931TwoDegree = 'cie1931-2';
    case Cie1964TenDegree = 'cie1964-10';
}
