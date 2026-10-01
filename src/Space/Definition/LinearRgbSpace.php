<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Definition;

use Domm98CZ\Color\Colorimetry\RgbPrimaries;
use Domm98CZ\Color\Colorimetry\WhitePoint;
use Domm98CZ\Color\Math\Matrix3;
use Domm98CZ\Color\Math\Vector3;
use Domm98CZ\Color\Space\Channel;
use Domm98CZ\Color\Space\ChannelKind;
use Domm98CZ\Color\Space\ComponentCategory;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Internal\SpaceIdentifier;

/**
 * Linear-light RGB defined by primaries and the white of its XYZ base. The RGB → XYZ matrix is the
 * normalised primary matrix (SMPTE RP 177-1993), see {@see RgbPrimaries::toXyzMatrix()}.
 */
final readonly class LinearRgbSpace implements ColorSpace
{
    /** @var non-empty-string */
    private string $id;

    /** @var non-empty-string */
    private string $name;

    private Matrix3 $toXyz;
    private Matrix3 $fromXyz;

    public function __construct(string $id, string $name, public RgbPrimaries $primaries, private XyzSpace $xyz)
    {
        $this->id = SpaceIdentifier::id($id);
        $this->name = SpaceIdentifier::name($name);
        $this->toXyz = $primaries->toXyzMatrix($xyz->whitePoint());
        $this->fromXyz = $this->toXyz->inverse();
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

    public function base(): XyzSpace
    {
        return $this->xyz;
    }

    public function toBase(array $coordinates): array
    {
        return $this->toXyz->transform(Vector3::fromList($coordinates))->toList();
    }

    public function fromBase(array $coordinates): array
    {
        return $this->fromXyz->transform(Vector3::fromList($coordinates))->toList();
    }

    public function whitePoint(): WhitePoint
    {
        return $this->xyz->whitePoint();
    }

    public function gamutSpace(): ColorSpace
    {
        return $this;
    }

    /**
     * Linear RGB → XYZ matrix of this space.
     */
    public function toXyzMatrix(): Matrix3
    {
        return $this->toXyz;
    }
}
