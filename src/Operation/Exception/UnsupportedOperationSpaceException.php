<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Operation\Exception;

use Domm98CZ\Color\Exception\DomainException;

/**
 * The operation is not defined for the given color space: quantisation outside a bounded RGB
 * space, a CSS gradient in a space that CSS cannot name as an interpolation space (e.g. `hsv`).
 */
final class UnsupportedOperationSpaceException extends DomainException {}
