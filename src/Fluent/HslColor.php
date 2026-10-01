<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Fluent;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Fluent\Result\FluentStep;

/**
 * A chain started from HSL with the scales of CSS `hsl()` (hue in degrees, saturation and
 * lightness 0–100): `(new HslColor(220, 60, 50))->tint(0.3)->toHex()`. {@see Color::fromHsl()}.
 */
final readonly class HslColor extends FluentColor
{
    public function __construct(float $hue, float $saturation, float $lightness, float $alpha = 1.0, ?FluentConfig $config = null)
    {
        $color = Color::fromHsl($hue, $saturation, $lightness, $alpha);

        parent::__construct($config ?? FluentConfig::defaults(), $color, [new FluentStep('color', ['hue' => $hue, 'saturation' => $saturation, 'lightness' => $lightness, 'alpha' => $alpha], $color)]);
    }
}
