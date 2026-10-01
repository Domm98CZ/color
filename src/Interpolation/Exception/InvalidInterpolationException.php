<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Interpolation\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * An interpolation or mix argument is invalid: a progress or a mix percentage outside [0, 1],
 * an empty list of mix items.
 */
final class InvalidInterpolationException extends InvalidArgumentException {}
