<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Fluent;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Fluent\Result\FluentStep;

/**
 * A chain started from 8-bit sRGB components: `(new RgbColor(51, 102, 204))->complement()->toHex()`.
 * {@see Color::fromRgb()}.
 */
final readonly class RgbColor extends FluentColor
{
    public function __construct(int $red, int $green, int $blue, ?FluentConfig $config = null)
    {
        $color = Color::fromRgb($red, $green, $blue);

        parent::__construct($config ?? FluentConfig::defaults(), $color, [new FluentStep('color', ['red' => $red, 'green' => $green, 'blue' => $blue], $color)]);
    }
}
