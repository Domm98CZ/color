<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Definition;

use Domm98CZ\Color\Colorimetry\WhitePoint;
use Domm98CZ\Color\Space\Channel;
use Domm98CZ\Color\Space\ChannelKind;
use Domm98CZ\Color\Space\ComponentCategory;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Internal\CylindricalRgb;
use Domm98CZ\Color\Space\Internal\SpaceIdentifier;

/**
 * HWB cylindrical form of an RGB space (CSS Color 4 § 8; Smith 1996).
 *
 * Hue is powerless when W + B ≥ 99.999 (CSS Color 4 § 8). Following § 4.4.1, when
 * 99.999 ≤ W + B < 100 the blackness is set to 100 − W so that the color is exactly achromatic.
 */
final readonly class HwbSpace implements ColorSpace
{
    public const float POWERLESS_THRESHOLD = 99.999;

    /** @var non-empty-string */
    private string $id;

    public function __construct(private RgbSpace $rgb, ?string $id = null)
    {
        $this->id = SpaceIdentifier::id($id ?? ($rgb->id() === 'srgb' ? 'hwb' : 'hwb@' . $rgb->id()));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return 'HWB (' . $this->rgb->name() . ')';
    }

    public function channels(): array
    {
        return [
            new Channel('h', 'Hue', ChannelKind::Hue, 0.0, 360.0, ComponentCategory::Hue),
            new Channel('w', 'Whiteness', ChannelKind::Percentage, 0.0, 100.0),
            new Channel('b', 'Blackness', ChannelKind::Percentage, 0.0, 100.0),
        ];
    }

    public function base(): RgbSpace
    {
        return $this->rgb;
    }

    public function toBase(array $coordinates): array
    {
        [$hue, $whiteness, $blackness] = $coordinates;

        return CylindricalRgb::hwbToRgb($hue, $whiteness, $blackness);
    }

    public function fromBase(array $coordinates): array
    {
        [$red, $green, $blue] = $coordinates;
        $hue = CylindricalRgb::rgbToHue($red, $green, $blue);
        $whiteness = \min($red, $green, $blue) * 100.0;
        $blackness = (1.0 - \max($red, $green, $blue)) * 100.0;
        $sum = $whiteness + $blackness;

        if ($sum >= self::POWERLESS_THRESHOLD) {
            if ($sum < 100.0) {
                $blackness = 100.0 - $whiteness;
            }

            return [null, $whiteness, $blackness];
        }

        return [$hue, $whiteness, $blackness];
    }

    public function whitePoint(): WhitePoint
    {
        return $this->rgb->whitePoint();
    }

    public function gamutSpace(): ColorSpace
    {
        return $this->rgb;
    }
}
