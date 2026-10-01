<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * A color value is invalid: wrong number of coordinates, NaN/INF, alpha outside [0, 1],
 * an 8-bit component outside 0–255…
 */
final class InvalidColorValueException extends InvalidArgumentException {}
