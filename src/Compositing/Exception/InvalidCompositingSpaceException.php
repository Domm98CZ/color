<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Compositing\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * Compositing averages coordinates, which is meaningless for hue angles; cylindrical spaces
 * (HSL, LCh, OKLCh…) cannot be compositing spaces.
 */
final class InvalidCompositingSpaceException extends InvalidArgumentException {}
