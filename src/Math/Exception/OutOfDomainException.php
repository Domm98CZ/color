<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Math\Exception;

use Domm98CZ\Color\Exception\DomainException;

/**
 * The arguments are finite but outside the domain of the function (e.g. 0 raised to a non-positive power).
 */
final class OutOfDomainException extends DomainException {}
