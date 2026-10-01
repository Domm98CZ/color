<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Record;

use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Internal\Coordinates;

/**
 * CIE XYZ relative to D65, white at Y = 1 (CSS `color(xyz-d65 x y z)`).
 *
 * Channels: x = x, y = y, z = z.
 */
final readonly class Xyz implements CoordinateRecord
{
    public float $x;
    public float $y;
    public float $z;
    public float $alpha;

    public function __construct(float $x, float $y, float $z, float $alpha = 1.0)
    {
        $this->x = Coordinates::finite($x, 'Xyz x');
        $this->y = Coordinates::finite($y, 'Xyz y');
        $this->z = Coordinates::finite($z, 'Xyz z');
        $this->alpha = Coordinates::alpha($alpha);
    }

    public static function space(): ColorSpace
    {
        return ColorSpaces::xyzD65();
    }

    public static function fromCoordinates(array $coordinates, float $alpha): static
    {
        [$x, $y, $z] = Coordinates::normalize(self::space(), $coordinates);

        return new static($x ?? 0.0, $y ?? 0.0, $z ?? 0.0, $alpha);
    }

    public function coordinates(): array
    {
        return [$this->x, $this->y, $this->z];
    }

    public function alpha(): float
    {
        return $this->alpha;
    }

    public function withX(float $x): static
    {
        return new static($x, $this->y, $this->z, $this->alpha);
    }

    public function withY(float $y): static
    {
        return new static($this->x, $y, $this->z, $this->alpha);
    }

    public function withZ(float $z): static
    {
        return new static($this->x, $this->y, $z, $this->alpha);
    }

    public function withAlpha(float $alpha): static
    {
        return new static($this->x, $this->y, $this->z, $alpha);
    }
}
