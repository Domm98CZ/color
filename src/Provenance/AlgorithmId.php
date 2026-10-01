<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Provenance;

use Domm98CZ\Color\Provenance\Exception\InvalidProvenanceException;

/**
 * Identity of an algorithm as implemented by this library.
 *
 * - `name` is stable and machine-readable (e.g. `ciede2000`, `css-color-4-binary-search`).
 * - `variant` describes parametrisation (e.g. `kL=2,kC=1,kH=1`), or null for the default form.
 * - `revision` is the version of *this library's implementation*; it is incremented whenever
 *   numeric behaviour changes.
 */
final readonly class AlgorithmId
{
    private const string NAME_PATTERN = '~^[a-z0-9]+(?:[.-][a-z0-9]+)*$~';

    /**
     * @param non-empty-string $name
     * @param positive-int $revision
     * @param list<SourceReference> $sources
     * @param non-empty-string|null $variant
     */
    public function __construct(
        public string $name,
        public int $revision = 1,
        public array $sources = [],
        public ?string $variant = null,
    ) {
        if (\preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidProvenanceException(\sprintf(
                'An algorithm name must be lowercase alphanumeric words separated by "-" or ".", "%s" given.',
                $name,
            ));
        }

        if ($revision < 1) {
            throw new InvalidProvenanceException(\sprintf('An algorithm revision must be >= 1, %d given.', $revision));
        }

        if ($variant !== null && \trim($variant) === '') {
            throw new InvalidProvenanceException('An algorithm variant must be null or non-empty.');
        }
    }

    /**
     * Compact identifier, e.g. `ciede2000[kL=2,kC=1,kH=1]@r1`.
     */
    public function identifier(): string
    {
        return $this->name . ($this->variant !== null ? '[' . $this->variant . ']' : '') . '@r' . $this->revision;
    }

    /**
     * Same algorithm, variant and revision. Sources are documentation and are not compared.
     */
    public function equals(self $other): bool
    {
        return $this->identifier() === $other->identifier();
    }
}
