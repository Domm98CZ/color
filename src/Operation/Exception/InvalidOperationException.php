<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Operation\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * An argument of an operation is invalid: a weight, amount or position outside its range, a
 * negative chroma scale, too few gradient stops, a quantisation depth outside 1–16 bits…
 */
final class InvalidOperationException extends InvalidArgumentException {}
