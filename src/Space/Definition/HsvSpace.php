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
 * HSV / HSB cylindrical form of an RGB space (A. R. Smith 1978). Not part of CSS.
 *
 * Implemented through its exact relation to HWB (A. R. Smith & E. R. Lyons 1996):
 * W = (1 − S)·V, B = 1 − V, so the CSS HWB conversions are reused.
 * V = max(R, G, B), S = (max − min) / max (0 when max = 0).
 *
 * Powerless hue: when the chroma max − min (= S·V) is ≤ 0.001 on the 0–100 scale — exactly the
 * condition under which the equivalent HWB color has W + B ≥ 99.999 (CSS Color 4 § 8). Deciding on
 * S alone would be wrong out of gamut, where V < 0 makes S negative. A positive S of a powerless
 * color is set to 0, as CSS Color 4 § 4.4.1 does for colorfulness.
 */
final readonly class HsvSpace implements ColorSpace
{
    /** Chroma (max − min, 0–100 scale) at or below which the hue is powerless. */
    public const float POWERLESS_CHROMA_EPSILON = 0.001;

    /** @var non-empty-string */
    private string $id;

    public function __construct(private RgbSpace $rgb, ?string $id = null)
    {
        $this->id = SpaceIdentifier::id($id ?? ($rgb->id() === 'srgb' ? 'hsv' : 'hsv@' . $rgb->id()));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return 'HSV (' . $this->rgb->name() . ')';
    }

    public function channels(): array
    {
        return [
            new Channel('h', 'Hue', ChannelKind::Hue, 0.0, 360.0, ComponentCategory::Hue),
            new Channel('s', 'Saturation', ChannelKind::Percentage, 0.0, 100.0, ComponentCategory::Colorfulness),
            new Channel('v', 'Value', ChannelKind::Percentage, 0.0, 100.0),
        ];
    }

    public function base(): RgbSpace
    {
        return $this->rgb;
    }

    public function toBase(array $coordinates): array
    {
        [$hue, $saturation, $value] = $coordinates;
        $saturation /= 100.0;
        $value /= 100.0;

        return CylindricalRgb::hwbToRgb($hue, (1.0 - $saturation) * $value * 100.0, (1.0 - $value) * 100.0);
    }

    public function fromBase(array $coordinates): array
    {
        [$red, $green, $blue] = $coordinates;
        $max = \max($red, $green, $blue);
        $min = \min($red, $green, $blue);
        $saturation = $max === 0.0 ? 0.0 : ($max - $min) / $max * 100.0;
        $value = $max * 100.0;

        if (($max - $min) * 100.0 <= self::POWERLESS_CHROMA_EPSILON) {
            return [null, $saturation > 0.0 ? 0.0 : $saturation, $value];
        }

        return [CylindricalRgb::rgbToHue($red, $green, $blue), $saturation, $value];
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
