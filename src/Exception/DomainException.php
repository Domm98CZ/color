<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Exception;

/**
 * The input is valid, but the requested operation is not defined for it
 * (e.g. serialising an out-of-gamut color to HEX, inverting a singular matrix).
 *
 * An expected negative outcome (a failed contrast check, a sample outside tolerance)
 * is a result, never this exception.
 */
abstract class DomainException extends \DomainException implements ColorException {}
