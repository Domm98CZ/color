<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * A palette, scale or catalog argument is invalid: duplicate or empty names, an unknown entry,
 * a step count below the minimum, a space without a lightness channel…
 */
final class InvalidPaletteException extends InvalidArgumentException {}
