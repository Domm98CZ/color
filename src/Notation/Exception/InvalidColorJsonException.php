<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Notation\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * A JSON document is not a valid color in the library's JSON schema.
 */
final class InvalidColorJsonException extends InvalidArgumentException {}
