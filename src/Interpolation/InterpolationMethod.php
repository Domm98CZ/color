<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Interpolation;

use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\ConversionOptions;

/**
 * The interpolation color space and hue method (CSS Color 4 § 13.2, `<color-interpolation-method>`).
 *
 * Any {@see ColorSpace} may be used, not only the CSS ones. The hue method applies to every hue
 * channel of the space and is ignored for spaces without one. The CSS default is Oklab with
 * `shorter` hue ({@see oklab()}).
 */
final readonly class InterpolationMethod
{
    public function __construct(
        public ColorSpace $space,
        public HueInterpolation $hue = HueInterpolation::Shorter,
        public ConversionOptions $conversion = new ConversionOptions(),
    ) {}

    /**
     * The CSS default: Oklab (CSS Color 4 § 13.2, CSS Color 5 § 3.1).
     */
    public static function oklab(): self
    {
        return new self(ColorSpaces::oklab());
    }

    public function isPolar(): bool
    {
        foreach ($this->space->channels() as $channel) {
            if ($channel->isHue()) {
                return true;
            }
        }

        return false;
    }
}
