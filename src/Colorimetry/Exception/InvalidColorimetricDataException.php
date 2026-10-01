<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Colorimetry\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * Colorimetric definition data are not usable (chromaticity with y ≤ 0, white point not normalised,
 * non-positive gamma, empty name…).
 */
final class InvalidColorimetricDataException extends InvalidArgumentException {}
