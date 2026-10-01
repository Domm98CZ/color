<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Conformance;

use Domm98CZ\Color\Tests\Support\Fixture\FixtureLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every file in fixtures/reference follows the schema (source, licence, justified tolerance).
 */
final class ReferenceFixturesAreValidTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function files(): iterable
    {
        foreach (FixtureLoader::allReferenceFiles() as $file) {
            yield \basename(\dirname($file)) . '/' . \basename($file) => [$file];
        }
    }

    #[DataProvider('files')]
    public function testFixtureIsValid(string $file): void
    {
        $fixture = FixtureLoader::fromFile($file);

        self::assertNotSame([], $fixture->cases);
    }

    public function testFixtureIdsAreUnique(): void
    {
        $ids = \array_map(
            static fn(string $file): string => FixtureLoader::fromFile($file)->id,
            FixtureLoader::allReferenceFiles(),
        );

        self::assertSame(\array_values(\array_unique($ids)), $ids);
    }
}
