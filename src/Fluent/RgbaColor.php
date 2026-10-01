<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Fluent;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Fluent\Result\FluentStep;

/**
 * A chain started from 8-bit sRGB components and an alpha 0–1:
 * `(new RgbaColor(51, 102, 204, 0.5))->mix('#fff')->toCss()`. {@see Color::fromRgba()}.
 */
final readonly class RgbaColor extends FluentColor
{
    public function __construct(int $red, int $green, int $blue, float $alpha, ?FluentConfig $config = null)
    {
        $color = Color::fromRgba($red, $green, $blue, $alpha);

        parent::__construct($config ?? FluentConfig::defaults(), $color, [new FluentStep('color', ['red' => $red, 'green' => $green, 'blue' => $blue, 'alpha' => $alpha], $color)]);
    }
}
