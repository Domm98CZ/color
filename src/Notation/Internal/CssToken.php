<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Notation\Internal;

/**
 * @internal one token of a CSS color value (CSS Syntax Level 3, the subset colors need)
 */
final readonly class CssToken
{
    /**
     * @param string $text identifier / function name / hash value / dimension unit as written
     * @param int $offset byte offset of the token in the input
     */
    public function __construct(
        public CssTokenType $type,
        public int $offset,
        public string $text = '',
        public float $number = 0.0,
    ) {}

    /**
     * The identifier, function name or unit in ASCII lowercase (CSS keywords are case-insensitive).
     */
    public function lowerText(): string
    {
        return \strtolower($this->text);
    }
}
