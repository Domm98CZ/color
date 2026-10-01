<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Provenance\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * Provenance data (algorithm id, source reference, warning, assumption…) is malformed.
 */
final class InvalidProvenanceException extends InvalidArgumentException {}
