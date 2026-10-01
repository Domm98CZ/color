<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Fluent;

use Domm98CZ\Color\Color;

/**
 * Entry point of the optional fluent layer:
 *
 *     Fluent::create()->color('#3366cc')->lighten(0.08)->mix('#ffcc00', 0.25)->toHex();
 *
 * It has no algorithms of its own: every step delegates to a service with the method from the
 * {@see FluentConfig}, and {@see FluentColor::trace()} records what was used.
 */
final readonly class Fluent
{
    public function __construct(private FluentConfig $config) {}

    /**
     * With {@see FluentConfig::defaults()}.
     */
    public static function create(): self
    {
        return new self(FluentConfig::defaults());
    }

    public function config(): FluentConfig
    {
        return $this->config;
    }

    /**
     * Starts a chain from a color or any CSS color string ({@see Color::parse()}).
     */
    public function color(Color|string $color): FluentColor
    {
        return \is_string($color)
            ? FluentColor::start($this->config, Color::parse($color), ['css' => $color])
            : FluentColor::start($this->config, $color, ['space' => $color->space()->id()]);
    }
}
