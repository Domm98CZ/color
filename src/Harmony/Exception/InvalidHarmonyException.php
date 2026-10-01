<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Harmony\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * An argument of a harmony scheme or wheel is invalid (a count below 1, a non-finite angle…).
 */
final class InvalidHarmonyException extends InvalidArgumentException {}
