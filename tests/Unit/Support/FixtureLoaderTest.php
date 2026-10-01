<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Support;

use Domm98CZ\Color\Tests\Support\Fixture\FixtureLoader;
use Domm98CZ\Color\Tests\Support\Fixture\InvalidFixtureException;
use Domm98CZ\Color\Tests\Support\SeededRandom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The test infrastructure itself is tested: a fixture without provenance must be rejected.
 */
final class FixtureLoaderTest extends TestCase
{
    /**
     * Builds a fixture document from a valid baseline with the given changes.
     *
     * @param array<string, mixed> $meta replaced meta keys
     * @param list<string> $removeMeta removed meta keys
     * @param array<string, mixed> $tolerance replaced meta.tolerance keys
     * @param list<string> $removeTolerance removed meta.tolerance keys
     * @param list<mixed>|null $cases replacement cases
     */
    private static function document(
        array $meta = [],
        array $removeMeta = [],
        array $tolerance = [],
        array $removeTolerance = [],
        ?array $cases = null,
    ): string {
        $baseTolerance = ['abs' => 1e-12, 'rationale' => 'Exact rationals.'];
        $baseMeta = [
            'id' => 'example',
            'source' => 'Example source',
            'retrieved' => '2026-09-28',
            'license' => 'CC0',
            'tolerance' => \array_diff_key(\array_replace($baseTolerance, $tolerance), \array_flip($removeTolerance)),
        ];

        return \json_encode([
            'meta' => \array_diff_key(\array_replace($baseMeta, $meta), \array_flip($removeMeta)),
            'cases' => $cases ?? [['value' => '1/3']],
        ], \JSON_THROW_ON_ERROR);
    }

    public function testLoadsValidFixture(): void
    {
        $fixture = FixtureLoader::fromJson(self::document(), 'inline');

        self::assertSame('example', $fixture->id);
        self::assertSame('Example source', $fixture->source);
        self::assertSame('2026-09-28', $fixture->retrieved);
        self::assertSame('CC0', $fixture->license);
        self::assertSame(1e-12, $fixture->tolerance->absolute);
        self::assertSame(0.0, $fixture->tolerance->relative);
        self::assertSame('Exact rationals.', $fixture->toleranceRationale);
        self::assertSame([['value' => '1/3']], $fixture->cases);
    }

    public function testAcceptsRelativeTolerance(): void
    {
        $fixture = FixtureLoader::fromJson(self::document(tolerance: ['rel' => 1e-9]), 'inline');

        self::assertSame(1e-9, $fixture->tolerance->relative);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDocuments(): iterable
    {
        yield 'not JSON' => ['{'];
        yield 'root is a scalar' => ['42'];
        yield 'missing meta' => [\json_encode(['cases' => [['a' => 1]]], \JSON_THROW_ON_ERROR)];
        yield 'missing source' => [self::document(removeMeta: ['source'])];
        yield 'blank license' => [self::document(meta: ['license' => ' '])];
        yield 'bad retrieved date' => [self::document(meta: ['retrieved' => 'yesterday'])];
        yield 'missing tolerance' => [self::document(removeMeta: ['tolerance'])];
        yield 'missing rationale' => [self::document(removeTolerance: ['rationale'])];
        yield 'missing absolute tolerance' => [self::document(removeTolerance: ['abs'])];
        yield 'negative tolerance' => [self::document(tolerance: ['abs' => -1])];
        yield 'string tolerance' => [self::document(tolerance: ['abs' => '1e-9'])];
        yield 'negative relative tolerance' => [self::document(tolerance: ['rel' => -0.1])];
        yield 'empty cases' => [self::document(cases: [])];
        yield 'case is a list' => [self::document(cases: [[1, 2]])];
        yield 'case is a scalar' => [self::document(cases: [5])];
    }

    #[DataProvider('invalidDocuments')]
    public function testRejectsInvalidFixtures(string $json): void
    {
        $this->expectException(InvalidFixtureException::class);

        FixtureLoader::fromJson($json, 'inline');
    }

    public function testNumberAcceptsNumbersAndRationals(): void
    {
        self::assertSame(2.0, FixtureLoader::number(2));
        self::assertSame(0.5, FixtureLoader::number(0.5));
        self::assertSame(1 / 3, FixtureLoader::number('1/3'));
        self::assertSame(-329 / 214, FixtureLoader::number('-329/214'));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidNumbers(): iterable
    {
        yield 'division by zero' => ['1/0'];
        yield 'decimal string' => ['0.5'];
        yield 'null' => [null];
    }

    #[DataProvider('invalidNumbers')]
    public function testNumberRejectsOtherValues(mixed $value): void
    {
        $this->expectException(InvalidFixtureException::class);

        FixtureLoader::number($value);
    }

    public function testMatrixParsesRows(): void
    {
        self::assertSame([[1.0, 0.5], [0.25, 2.0]], FixtureLoader::matrix([[1, '1/2'], [0.25, 2]]));

        $this->expectException(InvalidFixtureException::class);
        FixtureLoader::matrix([1, 2, 3]);
    }

    public function testSeededRandomIsDeterministic(): void
    {
        $a = new SeededRandom(42);
        $b = new SeededRandom(42);

        self::assertSame($a->float(0.0, 1.0), $b->float(0.0, 1.0));
        self::assertSame($a->int(0, 1000), $b->int(0, 1000));
        self::assertStringContainsString('COLORS_TEST_SEED=42', $a->describe());
    }
}
