<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Provenance;

use Domm98CZ\Color\Provenance\Exception\InvalidProvenanceException;
use Domm98CZ\Color\Provenance\SourceKind;
use Domm98CZ\Color\Provenance\SourceReference;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SourceReferenceTest extends TestCase
{
    public function testFullReference(): void
    {
        $source = new SourceReference(
            title: 'CSS Color Module Level 4',
            kind: SourceKind::Specification,
            edition: 'W3C Candidate Recommendation Draft, 26 September 2026',
            section: '§ 10',
            url: 'https://www.w3.org/TR/css-color-4/',
        );

        self::assertSame('CSS Color Module Level 4', $source->title);
        self::assertSame(SourceKind::Specification, $source->kind);
        self::assertSame('W3C Candidate Recommendation Draft, 26 September 2026', $source->edition);
        self::assertSame('§ 10', $source->section);
        self::assertSame('https://www.w3.org/TR/css-color-4/', $source->url);
    }

    public function testUserDefined(): void
    {
        $source = SourceReference::userDefined('ACME brand guidelines v3');

        self::assertSame(SourceKind::UserDefined, $source->kind);
        self::assertNull($source->edition);
        self::assertNull($source->url);
    }

    public function testRejectsBlankTitle(): void
    {
        $this->expectException(InvalidProvenanceException::class);

        new SourceReference(' ', SourceKind::Paper);
    }

    public function testRejectsBlankEdition(): void
    {
        $this->expectException(InvalidProvenanceException::class);

        /** @phpstan-ignore argument.type (deliberately invalid input) */
        new SourceReference('CIE 15', SourceKind::Standard, edition: '');
    }

    public function testRejectsBlankSection(): void
    {
        $this->expectException(InvalidProvenanceException::class);

        new SourceReference('CIE 15', SourceKind::Standard, section: ' ');
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function invalidUrls(): iterable
    {
        yield 'relative' => ['www.cie.co.at'];
        yield 'text before URL' => ['see https://www.cie.co.at'];
        yield 'text after URL' => ['https://www.cie.co.at and more'];
        yield 'other scheme' => ['ftp://example.org/file'];
    }

    /**
     * @param non-empty-string $url
     */
    #[DataProvider('invalidUrls')]
    public function testRejectsInvalidUrls(string $url): void
    {
        $this->expectException(InvalidProvenanceException::class);

        new SourceReference('CIE 15', SourceKind::Standard, url: $url);
    }

    public function testSourceKindValuesAreStable(): void
    {
        self::assertSame(
            ['standard', 'specification', 'paper', 'design-system', 'library-heuristic', 'user-defined'],
            \array_map(static fn(SourceKind $kind): string => $kind->value, SourceKind::cases()),
        );
    }
}
