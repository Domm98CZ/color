<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Contrast\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * A contrast range was requested for an empty set of backgrounds.
 */
final class EmptyBackgroundSetException extends InvalidArgumentException {}
