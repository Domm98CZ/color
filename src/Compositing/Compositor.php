<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Compositing;

use Domm98CZ\Color\Compositing\Algorithm\Composite;
use Domm98CZ\Color\Compositing\Algorithm\SourceOver;
use Domm98CZ\Color\Compositing\Contract\CompositeOperator;
use Domm98CZ\Color\Compositing\Exception\InvalidCompositingSpaceException;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Factories of compositing operators. The space is sRGB unless given; pass
 * `ColorSpaces::srgbLinear()` (or another linear-light RGB space) for the linear-light variant.
 *
 *     Compositor::sourceOver()->composite($top, $bottom);
 *     Compositor::blend(BlendMode::Multiply)->composite($top, $bottom);
 *     Compositor::porterDuff(PorterDuff::DestinationOut, ColorSpaces::srgbLinear());
 */
final class Compositor
{
    private function __construct() {}

    /**
     * Simple alpha compositing, the default everywhere (§ 5.1).
     *
     * @throws InvalidCompositingSpaceException
     */
    public static function sourceOver(?ColorSpace $space = null): CompositeOperator
    {
        return new SourceOver($space);
    }

    /**
     * @throws InvalidCompositingSpaceException
     */
    public static function porterDuff(PorterDuff $operator, ?ColorSpace $space = null): CompositeOperator
    {
        return $operator === PorterDuff::SourceOver ? new SourceOver($space) : new Composite($operator, BlendMode::Normal, $space);
    }

    /**
     * A blend mode followed by a Porter–Duff operator (default source-over, as CSS
     * `mix-blend-mode` does).
     *
     * @throws InvalidCompositingSpaceException when the space is not an RGB space
     */
    public static function blend(BlendMode $mode, PorterDuff $operator = PorterDuff::SourceOver, ?ColorSpace $space = null): CompositeOperator
    {
        return $mode === BlendMode::Normal ? self::porterDuff($operator, $space) : new Composite($operator, $mode, $space);
    }
}
