<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Definition;

use Domm98CZ\Color\Colorimetry\WhitePoint;
use Domm98CZ\Color\Math\Angle;
use Domm98CZ\Color\Space\Channel;
use Domm98CZ\Color\Space\ChannelKind;
use Domm98CZ\Color\Space\ComponentCategory;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Exception\InvalidColorSpaceDefinitionException;
use Domm98CZ\Color\Space\Internal\SpaceIdentifier;

/**
 * Cylindrical (lightness, chroma, hue) form of an opponent space with coordinates (L, a, b):
 * LCh over CIE Lab, OKLCh over OKLab (CSS Color 4 Lab_to_LCH / OKLab_to_OKLCH).
 *
 * C = √(a² + b²), h = atan2(b, a) in degrees [0, 360). When C ≤ ε the hue is powerless: it is
 * returned as null and a positive chroma is set to 0 (CSS Color 4 § 4.4.1).
 */
final readonly class PolarSpace implements ColorSpace
{
    /** @var non-empty-string */
    private string $id;

    /** @var non-empty-string */
    private string $name;

    /**
     * @param float $powerlessEpsilon chroma at or below which the hue is powerless (LCh 0.0015, OKLCh 0.000004)
     * @param float $chromaReference chroma corresponding to CSS 100% (LCh 150, OKLCh 0.4)
     */
    public function __construct(
        string $id,
        string $name,
        private ColorSpace $opponent,
        public float $powerlessEpsilon,
        private float $chromaReference,
    ) {
        $this->id = SpaceIdentifier::id($id);
        $this->name = SpaceIdentifier::name($name);

        if (\count($opponent->channels()) !== 3) {
            throw new InvalidColorSpaceDefinitionException('A polar space needs an opponent base space with exactly 3 channels (L, a, b).');
        }

        if (!\is_finite($powerlessEpsilon) || $powerlessEpsilon < 0.0 || !\is_finite($chromaReference) || $chromaReference <= 0.0) {
            throw new InvalidColorSpaceDefinitionException('A polar space needs a finite epsilon >= 0 and a finite chroma reference > 0.');
        }
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
        $lightness = $this->opponent->channels()[0];

        return [
            $lightness,
            new Channel('c', 'Chroma', ChannelKind::Number, 0.0, $this->chromaReference, ComponentCategory::Colorfulness),
            new Channel('h', 'Hue', ChannelKind::Hue, 0.0, 360.0, ComponentCategory::Hue),
        ];
    }

    public function base(): ColorSpace
    {
        return $this->opponent;
    }

    public function toBase(array $coordinates): array
    {
        [$lightness, $chroma, $hue] = $coordinates;
        $radians = Angle::degreesToRadians($hue);

        return [$lightness, $chroma * \cos($radians), $chroma * \sin($radians)];
    }

    public function fromBase(array $coordinates): array
    {
        [$lightness, $a, $b] = $coordinates;
        $chroma = \hypot($a, $b);

        if ($chroma <= $this->powerlessEpsilon) {
            return [$lightness, 0.0, null];
        }

        return [$lightness, $chroma, Angle::normalizeDegrees(Angle::radiansToDegrees(\atan2($b, $a)))];
    }

    public function whitePoint(): WhitePoint
    {
        return $this->opponent->whitePoint();
    }

    public function gamutSpace(): ?ColorSpace
    {
        return $this->opponent->gamutSpace();
    }
}
