<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * A color space definition is invalid (malformed id, empty name…).
 */
final class InvalidColorSpaceDefinitionException extends InvalidArgumentException {}
