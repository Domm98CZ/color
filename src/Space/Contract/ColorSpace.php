<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Contract;

use Domm98CZ\Color\Colorimetry\WhitePoint;
use Domm98CZ\Color\Space\Channel;

/**
 * A colorimetrically defined color space.
 *
 * Spaces form a conversion tree: each space knows its parent (`base()`) and converts to and from it.
 * The roots are XYZ spaces with a specific white; chromatic adaptation between roots is a choice of
 * the conversion ({@see \Domm98CZ\Color\Space\ConversionOptions}), not a property of a space.
 *
 * Implementations must be immutable and side-effect free.
 */
interface ColorSpace
{
    /**
     * Stable identifier, CSS-compatible where CSS defines one (e.g. 'srgb', 'oklch', 'xyz-d50').
     *
     * @return non-empty-string
     */
    public function id(): string;

    /**
     * @return non-empty-string
     */
    public function name(): string;

    /**
     * @return non-empty-list<Channel>
     */
    public function channels(): array;

    /**
     * Parent space in the conversion tree, or null for a root XYZ space.
     */
    public function base(): ?self;

    /**
     * Converts coordinates of this space to coordinates of {@see base()}.
     *
     * @param list<float> $coordinates
     *
     * @return list<float>
     */
    public function toBase(array $coordinates): array;

    /**
     * Converts coordinates of {@see base()} to coordinates of this space. A hue that is powerless for
     * the resulting color is returned as null (CSS Color 4 § 4.4.1).
     *
     * @param list<float> $coordinates
     *
     * @return list<float|null>
     */
    public function fromBase(array $coordinates): array;

    public function whitePoint(): WhitePoint;

    /**
     * The bounded RGB space whose [0, 1] cube defines the gamut of this space (itself for RGB spaces,
     * the underlying RGB space for HSL/HWB/HSV), or null when the space has no gamut boundary
     * (XYZ, Lab, OKLab…).
     */
    public function gamutSpace(): ?self;
}
