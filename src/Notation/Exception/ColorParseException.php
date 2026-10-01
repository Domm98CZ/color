<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Notation\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * A color string is not valid in the notation that was asked for (CSS, HEX).
 *
 * The offset is a byte offset into the input (0-based) where the problem was detected.
 */
final class ColorParseException extends InvalidArgumentException
{
    public function __construct(
        private readonly string $input,
        private readonly int $offset,
        private readonly string $expected,
        string $problem,
    ) {
        parent::__construct(\sprintf('Cannot parse color "%s" at offset %d: %s; expected %s.', $input, $offset, $problem, $expected));
    }

    public function getInput(): string
    {
        return $this->input;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * What the parser expected at the offset, in words (e.g. "3, 4, 6 or 8 hexadecimal digits").
     */
    public function getExpected(): string
    {
        return $this->expected;
    }
}
