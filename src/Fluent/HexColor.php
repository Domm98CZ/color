<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Fluent;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Fluent\Result\FluentStep;

/**
 * A chain started from a hex color: `(new HexColor('#3366cc'))->lighten(0.1)->toHex()`.
 * {@see Color::fromHex()} for the accepted forms.
 */
final readonly class HexColor extends FluentColor
{
    public function __construct(string $hex, ?FluentConfig $config = null)
    {
        $color = Color::fromHex($hex);

        parent::__construct($config ?? FluentConfig::defaults(), $color, [new FluentStep('color', ['hex' => $hex], $color)]);
    }
}
