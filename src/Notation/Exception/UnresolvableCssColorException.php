<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Notation\Exception;

use Domm98CZ\Color\Exception\DomainException;

/**
 * The CSS value is a valid color, but it depends on a document context that a color library does
 * not have: `currentcolor`, system colors (`Canvas`, `ButtonText`…), deprecated system colors and
 * `light-dark()`. The library never substitutes a guess for them.
 */
final class UnresolvableCssColorException extends DomainException
{
    public function __construct(private readonly string $input, private readonly string $keyword)
    {
        parent::__construct(\sprintf('The CSS color "%s" uses "%s", which depends on the document (element, color scheme or user settings) and cannot be resolved to a color here.', $input, $keyword));
    }

    public function getInput(): string
    {
        return $this->input;
    }

    /**
     * The keyword or function name in lowercase, e.g. "currentcolor", "canvastext", "light-dark".
     */
    public function getKeyword(): string
    {
        return $this->keyword;
    }
}
