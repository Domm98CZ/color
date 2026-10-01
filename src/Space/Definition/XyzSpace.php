<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Definition;

use Domm98CZ\Color\Colorimetry\WhitePoint;
use Domm98CZ\Color\Space\Channel;
use Domm98CZ\Color\Space\ChannelKind;
use Domm98CZ\Color\Space\ComponentCategory;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Internal\SpaceIdentifier;

/**
 * CIE XYZ relative to a given white, normalised so that the white has Y = 1.
 * XYZ spaces are the roots of the conversion tree.
 */
final readonly class XyzSpace implements ColorSpace
{
    /** @var non-empty-string */
    private string $id;

    /** @var non-empty-string */
    private string $name;

    public function __construct(string $id, string $name, private WhitePoint $white)
    {
        $this->id = SpaceIdentifier::id($id);
        $this->name = SpaceIdentifier::name($name);
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
            new Channel('x', 'X', ChannelKind::Number, 0.0, 1.0, ComponentCategory::Reds),
            new Channel('y', 'Y', ChannelKind::Number, 0.0, 1.0, ComponentCategory::Greens),
            new Channel('z', 'Z', ChannelKind::Number, 0.0, 1.0, ComponentCategory::Blues),
        ];
    }

    public function base(): ?ColorSpace
    {
        return null;
    }

    /**
     * A root has no base; the coordinates are returned unchanged.
     */
    public function toBase(array $coordinates): array
    {
        return $coordinates;
    }

    /**
     * A root has no base; the coordinates are returned unchanged.
     */
    public function fromBase(array $coordinates): array
    {
        return $coordinates;
    }

    public function whitePoint(): WhitePoint
    {
        return $this->white;
    }

    public function gamutSpace(): ?ColorSpace
    {
        return null;
    }
}
