<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Contrast\Exception;

use Domm98CZ\Color\Exception\InsufficientDataException;

/**
 * The background is not opaque, so what is behind it is unknown. The library does not assume a
 * white page; pass the backdrop explicitly, e.g. LayerStack::of($translucent)->over($page).
 */
final class MissingBackdropException extends InsufficientDataException
{
    public static function forAlpha(float $alpha): self
    {
        return new self(
            \sprintf('The background has alpha %s; provide an opaque backdrop, e.g. LayerStack::of($background)->over($page).', \var_export($alpha, true)),
            ['opaque backdrop'],
        );
    }
}
