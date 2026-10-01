<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Record;

use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Internal\Coordinates;

/**
 * sRGB components on the 0–255 scale (as CSS `rgb()`), not rounded; values outside [0, 255] are out of gamut.
 *
 * Channels: r = red, g = green, b = blue.
 */
final readonly class Rgb255 implements CoordinateRecord
{
    private const float SCALE = 255.0;

    public float $r;
    public float $g;
    public float $b;
    public float $alpha;

    public function __construct(float $r, float $g, float $b, float $alpha = 1.0)
    {
        $this->r = Coordinates::finite($r, 'Rgb255 r');
        $this->g = Coordinates::finite($g, 'Rgb255 g');
        $this->b = Coordinates::finite($b, 'Rgb255 b');
        $this->alpha = Coordinates::alpha($alpha);
    }

    public static function space(): ColorSpace
    {
        return ColorSpaces::srgb();
    }

    public static function fromCoordinates(array $coordinates, float $alpha): static
    {
        [$r, $g, $b] = Coordinates::normalize(self::space(), $coordinates);

        return new static(($r ?? 0.0) * self::SCALE, ($g ?? 0.0) * self::SCALE, ($b ?? 0.0) * self::SCALE, $alpha);
    }

    public function coordinates(): array
    {
        return [$this->r / self::SCALE, $this->g / self::SCALE, $this->b / self::SCALE];
    }

    public function alpha(): float
    {
        return $this->alpha;
    }

    public function withR(float $r): static
    {
        return new static($r, $this->g, $this->b, $this->alpha);
    }

    public function withG(float $g): static
    {
        return new static($this->r, $g, $this->b, $this->alpha);
    }

    public function withB(float $b): static
    {
        return new static($this->r, $this->g, $b, $this->alpha);
    }

    public function withAlpha(float $alpha): static
    {
        return new static($this->r, $this->g, $this->b, $alpha);
    }
}
