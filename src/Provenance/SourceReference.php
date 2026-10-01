<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Provenance;

use Domm98CZ\Color\Provenance\Exception\InvalidProvenanceException;

/**
 * Citation of the source that defines an algorithm, constant or rule.
 */
final readonly class SourceReference
{
    /**
     * @param non-empty-string $title e.g. 'CSS Color Module Level 4'
     * @param non-empty-string|null $edition e.g. 'W3C Candidate Recommendation Draft, 26 September 2026'
     * @param non-empty-string|null $section e.g. '§ 10.2'
     * @param non-empty-string|null $url absolute http(s) URL
     */
    public function __construct(
        public string $title,
        public SourceKind $kind,
        public ?string $edition = null,
        public ?string $section = null,
        public ?string $url = null,
    ) {
        if (\trim($title) === '') {
            throw new InvalidProvenanceException('A source reference title must not be empty.');
        }

        foreach (['edition' => $edition, 'section' => $section] as $field => $value) {
            if ($value !== null && \trim($value) === '') {
                throw new InvalidProvenanceException(\sprintf('A source reference %s must be null or non-empty.', $field));
            }
        }

        if ($url !== null && \preg_match('~^https?://\S+$~', $url) !== 1) {
            throw new InvalidProvenanceException(\sprintf('A source reference URL must be an absolute http(s) URL, "%s" given.', $url));
        }
    }

    /**
     * A rule or value supplied by the library user (e.g. a brand guideline).
     *
     * @param non-empty-string $title
     */
    public static function userDefined(string $title): self
    {
        return new self($title, SourceKind::UserDefined);
    }
}
