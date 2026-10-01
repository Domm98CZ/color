<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Notation\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * A serialisation option of {@see \Domm98CZ\Color\Notation\CssFormat} is out of range.
 */
final class InvalidCssFormatException extends InvalidArgumentException {}
