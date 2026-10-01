<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Fake;

use Domm98CZ\Color\Colorimetry\WhitePoint;
use Domm98CZ\Color\Space\Channel;
use Domm98CZ\Color\Space\ChannelKind;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * A user-defined color space: sRGB with components on a 0–100 scale. Used to prove that the
 * ColorSpace contract is sufficient for extensions (conversion, registry, gamut, Color).
 */
final class PercentSrgbSpace implements ColorSpace
{
    /**
     * @param bool $ownGamut true: the space is its own gamut space (0–100 cube) instead of sRGB
     */
    public function __construct(private readonly bool $ownGamut = false) {}

    public function id(): string
    {
        return 'test-percent-srgb';
    }

    public function name(): string
    {
        return 'sRGB in percent (test)';
    }

    public function channels(): array
    {
        return [
            new Channel('r', 'Red', ChannelKind::Percentage, 0.0, 100.0),
            new Channel('g', 'Green', ChannelKind::Percentage, 0.0, 100.0),
            new Channel('b', 'Blue', ChannelKind::Percentage, 0.0, 100.0),
        ];
    }

    public function base(): ColorSpace
    {
        return ColorSpaces::srgb();
    }

    public function toBase(array $coordinates): array
    {
        return \array_map(static fn(float $v): float => $v / 100.0, $coordinates);
    }

    public function fromBase(array $coordinates): array
    {
        return \array_map(static fn(float $v): float => $v * 100.0, $coordinates);
    }

    public function whitePoint(): WhitePoint
    {
        return ColorSpaces::srgb()->whitePoint();
    }

    public function gamutSpace(): ColorSpace
    {
        return $this->ownGamut ? $this : ColorSpaces::srgb();
    }
}
