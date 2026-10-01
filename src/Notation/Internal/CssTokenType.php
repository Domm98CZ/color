<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Notation\Internal;

/**
 * @internal
 */
enum CssTokenType
{
    case Ident;
    /** An identifier immediately followed by "(" (the parenthesis is part of the token). */
    case Function;
    case Hash;
    case Number;
    case Percentage;
    case Dimension;
    case Comma;
    case Slash;
    case CloseParen;
}
