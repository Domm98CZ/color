<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Provenance;

use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Provenance\Exception\InvalidProvenanceException;
use Domm98CZ\Color\Provenance\SourceKind;
use Domm98CZ\Color\Provenance\SourceReference;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AlgorithmIdTest extends TestCase
{
    public function testDefaults(): void
    {
        $id = new AlgorithmId('ciede2000');

        self::assertSame('ciede2000', $id->name);
        self::assertSame(1, $id->revision);
        self::assertSame([], $id->sources);
        self::assertNull($id->variant);
        self::assertSame('ciede2000@r1', $id->identifier());
    }

    public function testIdentifierIncludesVariantAndRevision(): void
    {
        $id = new AlgorithmId('ciede2000', revision: 3, variant: 'kL=2,kC=1,kH=1');

        self::assertSame('ciede2000[kL=2,kC=1,kH=1]@r3', $id->identifier());
    }

    public function testEqualityIgnoresSourcesButNotVariantOrRevision(): void
    {
        $source = new SourceReference('CIE 142-2001', SourceKind::Standard);
        $plain = new AlgorithmId('ciede2000');

        self::assertTrue($plain->equals(new AlgorithmId('ciede2000', sources: [$source])));
        self::assertFalse($plain->equals(new AlgorithmId('ciede2000', revision: 2)));
        self::assertFalse($plain->equals(new AlgorithmId('ciede2000', variant: 'kL=2')));
        self::assertFalse($plain->equals(new AlgorithmId('cie94')));
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function validNames(): iterable
    {
        yield 'simple' => ['cie76'];
        yield 'dashes' => ['css-color-4-binary-search'];
        yield 'dots' => ['apca.w3'];
        yield 'digits only' => ['2000'];
    }

    /**
     * @param non-empty-string $name
     */
    #[DataProvider('validNames')]
    public function testAcceptsValidNames(string $name): void
    {
        self::assertSame($name, (new AlgorithmId($name))->name);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['CIEDE2000'];
        yield 'space' => ['delta e'];
        yield 'leading dash' => ['-oklab'];
        yield 'trailing dash' => ['oklab-'];
        yield 'double dash' => ['ok--lab'];
        yield 'underscore' => ['delta_e'];
    }

    #[DataProvider('invalidNames')]
    public function testRejectsInvalidNames(string $name): void
    {
        $this->expectException(InvalidProvenanceException::class);

        /** @phpstan-ignore argument.type (deliberately invalid input) */
        new AlgorithmId($name);
    }

    public function testRejectsNonPositiveRevision(): void
    {
        $this->expectException(InvalidProvenanceException::class);

        /** @phpstan-ignore argument.type (deliberately invalid input) */
        new AlgorithmId('cie76', revision: 0);
    }

    public function testRejectsBlankVariant(): void
    {
        $this->expectException(InvalidProvenanceException::class);

        new AlgorithmId('cie76', variant: '  ');
    }
}
