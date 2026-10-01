<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Notation\Internal;

use Domm98CZ\Color\Notation\Exception\ColorParseException;

/**
 * @internal tokenizer for a single CSS color value
 *
 * Follows CSS Syntax Level 3 § 4 for the tokens a color can contain: whitespace and comments are
 * dropped (juxtaposed tokens need no whitespace between them, e.g. `rgb(1+2+3)`), numbers use the
 * CSS grammar (sign, fraction, exponent), and identifiers are ASCII-case-insensitive. Escape
 * sequences, strings, URLs and a bare "(" are not needed by any color syntax and are rejected.
 */
final class CssTokenizer
{
    private const string NUMBER = '/\G[+-]?(?:\d+(?:\.\d+)?|\.\d+)(?:[eE][+-]?\d+)?/';
    private const string IDENT = '/\G(?:--|-?[a-zA-Z_\x80-\xFF])[a-zA-Z0-9_\-\x80-\xFF]*/';
    private const string HASH = '/\G#([a-zA-Z0-9_\-\x80-\xFF]+)/';
    private const string WHITESPACE = '/\G[ \t\n\r\f]+/';

    private function __construct() {}

    /**
     * @return list<CssToken>
     *
     * @throws ColorParseException
     */
    public static function tokenize(string $input): array
    {
        $tokens = [];
        $length = \strlen($input);
        $offset = 0;

        while ($offset < $length) {
            if (\preg_match(self::WHITESPACE, $input, $match, 0, $offset) === 1) {
                $offset += \strlen($match[0]);
                continue;
            }

            if (\substr($input, $offset, 2) === '/*') {
                $end = \strpos($input, '*/', $offset + 2);
                $offset = $end === false ? $length : $end + 2;
                continue;
            }

            if (\preg_match(self::NUMBER, $input, $match, 0, $offset) === 1) {
                [$token, $consumed] = self::numeric($input, $offset, $match[0]);
                $tokens[] = $token;
                $offset += $consumed;
                continue;
            }

            if (\preg_match(self::IDENT, $input, $match, 0, $offset) === 1) {
                $end = $offset + \strlen($match[0]);
                if (($input[$end] ?? '') === '(') {
                    $tokens[] = new CssToken(CssTokenType::Function, $offset, $match[0]);
                    $offset = $end + 1;
                } else {
                    $tokens[] = new CssToken(CssTokenType::Ident, $offset, $match[0]);
                    $offset = $end;
                }
                continue;
            }

            if (\preg_match(self::HASH, $input, $match, 0, $offset) === 1) {
                $tokens[] = new CssToken(CssTokenType::Hash, $offset, $match[1]);
                $offset += \strlen($match[0]);
                continue;
            }

            $tokens[] = match ($input[$offset]) {
                ',' => new CssToken(CssTokenType::Comma, $offset),
                '/' => new CssToken(CssTokenType::Slash, $offset),
                ')' => new CssToken(CssTokenType::CloseParen, $offset),
                default => throw new ColorParseException($input, $offset, 'a number, a keyword, a color function, "#", ",", "/" or ")"', \sprintf('unexpected character "%s"', $input[$offset])),
            };
            $offset++;
        }

        return $tokens;
    }

    /**
     * A number followed by "%" is a percentage; followed by an identifier it is a dimension
     * (e.g. `90deg`); otherwise a plain number.
     *
     * @return array{CssToken, int} the token and the number of bytes it spans
     */
    private static function numeric(string $input, int $offset, string $number): array
    {
        $value = (float) $number;

        if (!\is_finite($value)) {
            throw new ColorParseException($input, $offset, 'a finite number', \sprintf('the number "%s" is out of range', $number));
        }

        $end = $offset + \strlen($number);

        if (($input[$end] ?? '') === '%') {
            return [new CssToken(CssTokenType::Percentage, $offset, '%', $value), \strlen($number) + 1];
        }

        if (\preg_match(self::IDENT, $input, $unit, 0, $end) === 1) {
            return [new CssToken(CssTokenType::Dimension, $offset, $unit[0], $value), \strlen($number) + \strlen($unit[0])];
        }

        return [new CssToken(CssTokenType::Number, $offset, '', $value), \strlen($number)];
    }
}
