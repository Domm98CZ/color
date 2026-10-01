<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Fluent;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Fluent\Result\FluentStep;

/**
 * A chain started from OKLCh (L 0–1, chroma, hue in degrees):
 * `(new OklchColor(0.6, 0.15, 260))->toHex()`. {@see Color::fromOklch()}.
 */
final readonly class OklchColor extends FluentColor
{
    public function __construct(float $lightness, float $chroma, float $hue, float $alpha = 1.0, ?FluentConfig $config = null)
    {
        $color = Color::fromOklch($lightness, $chroma, $hue, $alpha);

        parent::__construct($config ?? FluentConfig::defaults(), $color, [new FluentStep('color', ['lightness' => $lightness, 'chroma' => $chroma, 'hue' => $hue, 'alpha' => $alpha], $color)]);
    }
}
