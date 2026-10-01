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
 * HSL cylindrical form of an RGB space (CSS Color 4 § 7). CSS defines `hsl()` over sRGB only; over
 * other RGB spaces the id is `hsl@<rgb-id>` and the space is not expressible in CSS.
 *
 * Hue is powerless when S ≤ 0.001 (CSS Color 4 § 7); it is then returned as null and a positive
 * saturation below ε is set to 0 (§ 4.4.1).
 */
final readonly class HslSpace implements ColorSpace
{
    public const float POWERLESS_EPSILON = 0.001;

    /** @var non-empty-string */
    private string $id;

    public function __construct(private RgbSpace $rgb, ?string $id = null)
    {
        $this->id = SpaceIdentifier::id($id ?? ($rgb->id() === 'srgb' ? 'hsl' : 'hsl@' . $rgb->id()));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return 'HSL (' . $this->rgb->name() . ')';
    }

    public function channels(): array
    {
        return [
            new Channel('h', 'Hue', ChannelKind::Hue, 0.0, 360.0, ComponentCategory::Hue),
            new Channel('s', 'Saturation', ChannelKind::Percentage, 0.0, 100.0, ComponentCategory::Colorfulness),
            new Channel('l', 'Lightness', ChannelKind::Percentage, 0.0, 100.0, ComponentCategory::Lightness),
        ];
    }

    public function base(): RgbSpace
    {
        return $this->rgb;
    }

    public function toBase(array $coordinates): array
    {
        [$hue, $saturation, $lightness] = $coordinates;

        return CylindricalRgb::hslToRgb($hue, $saturation, $lightness);
    }

    public function fromBase(array $coordinates): array
    {
        [$hue, $saturation, $lightness] = CylindricalRgb::rgbToHsl(...$coordinates);

        if ($saturation <= self::POWERLESS_EPSILON || \is_nan($hue)) {
            // rgbToHsl() never returns a negative saturation, so the whole branch is S in [0, ε]:
            // CSS Color 4 §4.4.1 maps it to exactly 0.
            return [null, 0.0, $lightness];
        }

        return [$hue, $saturation, $lightness];
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
