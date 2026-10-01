<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Support\Fixture;

use Domm98CZ\Color\Math\Tolerance;

/**
 * A validated reference-data file: provenance metadata, a justified tolerance and test cases.
 */
final readonly class ReferenceFixture
{
    /**
     * @param non-empty-string $id
     * @param non-empty-string $source
     * @param non-empty-string $retrieved YYYY-MM-DD
     * @param non-empty-string $license
     * @param non-empty-string $toleranceRationale
     * @param non-empty-list<array<string, mixed>> $cases
     * @param array<string, mixed> $meta the complete metadata object
     */
    public function __construct(
        public string $id,
        public string $source,
        public string $retrieved,
        public string $license,
        public Tolerance $tolerance,
        public string $toleranceRationale,
        public array $cases,
        public array $meta,
    ) {}
}
