<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Exception;

/**
 * The input is invalid on its own (NaN, out-of-range value, malformed string, wrong arity…).
 *
 * Concrete exceptions live in the module that detects the problem.
 */
abstract class InvalidArgumentException extends \InvalidArgumentException implements ColorException {}
