<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Definition;

use Domm98CZ\Color\Colorimetry\Contract\TransferFunction;
use Domm98CZ\Color\Colorimetry\RgbPrimaries;
use Domm98CZ\Color\Colorimetry\WhitePoint;
use Domm98CZ\Color\Space\Channel;
use Domm98CZ\Color\Space\ChannelKind;
use Domm98CZ\Color\Space\ComponentCategory;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Internal\SpaceIdentifier;

/**
 * Encoded (gamma-corrected) RGB: a transfer function over a {@see LinearRgbSpace}.
 * In-gamut colors have all components in [0, 1]; values outside are kept, never clipped.
 */
final readonly class RgbSpace implements ColorSpace
{
    /** @var non-empty-string */
    private string $id;

    /** @var non-empty-string */
    private string $name;

    public function __construct(string $id, string $name, public TransferFunction $transfer, private LinearRgbSpace $linear)
    {
        $this->id = SpaceIdentifier::id($id);
        $this->name = SpaceIdentifier::name($name);
    }

    /**
     * Defines an RGB space from primaries, white and transfer function. The XYZ base must be the root
     * space for the white (e.g. ColorSpaces::xyzD65()) so that conversions find a common ancestor.
     */
    public static function fromPrimaries(
        string $id,
        string $name,
        RgbPrimaries $primaries,
        XyzSpace $xyz,
        TransferFunction $transfer,
    ): self {
        return new self($id, $name, $transfer, new LinearRgbSpace($id . '-linear', $name . ' (linear)', $primaries, $xyz));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function channels(): array
    {
        return [
            new Channel('r', 'Red', ChannelKind::Number, 0.0, 1.0, ComponentCategory::Reds),
            new Channel('g', 'Green', ChannelKind::Number, 0.0, 1.0, ComponentCategory::Greens),
            new Channel('b', 'Blue', ChannelKind::Number, 0.0, 1.0, ComponentCategory::Blues),
        ];
    }

    public function base(): LinearRgbSpace
    {
        return $this->linear;
    }

    public function toBase(array $coordinates): array
    {
        return \array_map($this->transfer->toLinear(...), $coordinates);
    }

    public function fromBase(array $coordinates): array
    {
        return \array_map($this->transfer->toEncoded(...), $coordinates);
    }

    public function whitePoint(): WhitePoint
    {
        return $this->linear->whitePoint();
    }

    public function gamutSpace(): ColorSpace
    {
        return $this;
    }
}
