<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Gamut\Exception;

use Domm98CZ\Color\Exception\DomainException;

/**
 * The gamut mapper cannot work with the destination (e.g. ray trace needs an RGB gamut whose
 * linear-light form is a [0, 1] cube).
 */
final class UnsupportedGamutException extends DomainException {}
