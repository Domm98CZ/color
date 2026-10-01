<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Provenance;

use Domm98CZ\Color\Provenance\Exception\InvalidProvenanceException;

/**
 * A condition under which a result is valid, e.g. "the digital color is interpreted
 * under D50 via linear Bradford adaptation".
 */
final readonly class Assumption
{
    private const string CODE_PATTERN = '~^[a-z0-9]+(?:-[a-z0-9]+)*$~';

    /**
     * @param non-empty-string $code stable machine-readable kebab-case code
     * @param non-empty-string $description human-readable explanation
     */
    public function __construct(
        public string $code,
        public string $description,
    ) {
        if (\preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw new InvalidProvenanceException(\sprintf('An assumption code must be kebab-case, "%s" given.', $code));
        }

        if (\trim($description) === '') {
            throw new InvalidProvenanceException('An assumption description must not be empty.');
        }
    }
}
