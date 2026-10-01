<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Fluent;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Fluent\Result\FluentStep;

/**
 * A chain started from any CSS Color 4 `<color>`, e.g. 'rebeccapurple', 'rgb(51 102 204 / 50%)',
 * 'oklch(62% 0.18 255)': `(new CssColor('rebeccapurple'))->lighten(0.1)->toCss()`.
 * {@see Color::parse()}.
 */
final readonly class CssColor extends FluentColor
{
    public function __construct(string $css, ?FluentConfig $config = null)
    {
        $color = Color::parse($css);

        parent::__construct($config ?? FluentConfig::defaults(), $color, [new FluentStep('color', ['css' => $css], $color)]);
    }
}
