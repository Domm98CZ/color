<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Definition;

use Domm98CZ\Color\Colorimetry\WhitePoint;
use Domm98CZ\Color\Math\Numeric;
use Domm98CZ\Color\Space\Channel;
use Domm98CZ\Color\Space\ChannelKind;
use Domm98CZ\Color\Space\ComponentCategory;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Internal\SpaceIdentifier;

/**
 * CIE 1976 L*a*b* relative to the white of its XYZ base (CIE 15:2018; formulas and the rational
 * constants ε = 216/24389, κ = 24389/27 as in CSS Color 4 XYZ_to_Lab / Lab_to_XYZ).
 *
 * CSS `lab()` is relative to D50; `lab-d65` is provided for computations that need D65.
 */
final readonly class LabSpace implements ColorSpace
{
    /** 6³/29³ */
    public const float EPSILON = 216 / 24389;

    /** 29³/3³ */
    public const float KAPPA = 24389 / 27;

    /** @var non-empty-string */
    private string $id;

    /** @var non-empty-string */
    private string $name;

    public function __construct(string $id, string $name, private XyzSpace $xyz)
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
            new Channel('l', 'Lightness', ChannelKind::Number, 0.0, 100.0, ComponentCategory::Lightness),
            new Channel('a', 'a', ChannelKind::Number, -125.0, 125.0, ComponentCategory::OpponentA),
            new Channel('b', 'b', ChannelKind::Number, -125.0, 125.0, ComponentCategory::OpponentB),
        ];
    }

    public function base(): XyzSpace
    {
        return $this->xyz;
    }

    public function toBase(array $coordinates): array
    {
        [$lightness, $a, $b] = $coordinates;
        $white = $this->xyz->whitePoint()->xyz;

        $fy = ($lightness + 16.0) / 116.0;
        $fx = $a / 500.0 + $fy;
        $fz = $fy - $b / 200.0;

        $x = $fx ** 3 > self::EPSILON ? $fx ** 3 : (116.0 * $fx - 16.0) / self::KAPPA;
        $y = $lightness > self::KAPPA * self::EPSILON ? $fy ** 3 : $lightness / self::KAPPA;
        $z = $fz ** 3 > self::EPSILON ? $fz ** 3 : (116.0 * $fz - 16.0) / self::KAPPA;

        return [$x * $white->x, $y * $white->y, $z * $white->z];
    }

    public function fromBase(array $coordinates): array
    {
        [$x, $y, $z] = $coordinates;
        $white = $this->xyz->whitePoint()->xyz;

        $fx = self::f($x / $white->x);
        $fy = self::f($y / $white->y);
        $fz = self::f($z / $white->z);

        return [116.0 * $fy - 16.0, 500.0 * ($fx - $fy), 200.0 * ($fy - $fz)];
    }

    public function whitePoint(): WhitePoint
    {
        return $this->xyz->whitePoint();
    }

    public function gamutSpace(): ?ColorSpace
    {
        return null;
    }

    private static function f(float $t): float
    {
        return $t > self::EPSILON ? Numeric::cbrt($t) : (self::KAPPA * $t + 16.0) / 116.0;
    }
}
