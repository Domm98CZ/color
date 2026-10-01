<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Provenance;

use Domm98CZ\Color\Provenance\Exception\InvalidProvenanceException;

/**
 * Structured warning attached to a result. The library never logs; warnings travel with results.
 */
final readonly class Warning
{
    /**
     * @param non-empty-string $message specific, human-readable message
     * @param array<non-empty-string, scalar|null> $context machine-readable details
     */
    public function __construct(
        public WarningCode $code,
        public string $message,
        public array $context = [],
    ) {
        if (\trim($message) === '') {
            throw new InvalidProvenanceException('A warning message must not be empty.');
        }

        foreach ($context as $key => $value) {
            if (!\is_string($key) || $key === '') {
                throw new InvalidProvenanceException('Warning context keys must be non-empty strings.');
            }

            if ($value !== null && !\is_scalar($value)) {
                throw new InvalidProvenanceException(\sprintf('Warning context value "%s" must be a scalar or null.', $key));
            }
        }
    }
}
