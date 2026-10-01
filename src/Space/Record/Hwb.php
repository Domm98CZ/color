<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Record;

use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Internal\Coordinates;

/**
 * HWB over sRGB: hue in degrees (null when powerless), whiteness and blackness 0–100 as in CSS `hwb()`.
 *
 * Channels: h = hue, w = whiteness, b = blackness.
 */
final readonly class Hwb implements CoordinateRecord
{
    public ?float $h;
    public float $w;
    public float $b;
    public float $alpha;

    public function __construct(?float $h, float $w, float $b, float $alpha = 1.0)
    {
        $this->h = Coordinates::hue($h);
        $this->w = Coordinates::finite($w, 'Hwb w');
        $this->b = Coordinates::finite($b, 'Hwb b');
        $this->alpha = Coordinates::alpha($alpha);
    }

    public static function space(): ColorSpace
    {
        return ColorSpaces::hwb();
    }

    public static function fromCoordinates(array $coordinates, float $alpha): static
    {
        [$h, $w, $b] = Coordinates::normalize(self::space(), $coordinates);

        return new static($h, $w ?? 0.0, $b ?? 0.0, $alpha);
    }

    public function coordinates(): array
    {
        return [$this->h, $this->w, $this->b];
    }

    public function alpha(): float
    {
        return $this->alpha;
    }

    public function withH(?float $h): static
    {
        return new static($h, $this->w, $this->b, $this->alpha);
    }

    public function withW(float $w): static
    {
        return new static($this->h, $w, $this->b, $this->alpha);
    }

    public function withB(float $b): static
    {
        return new static($this->h, $this->w, $b, $this->alpha);
    }

    public function withAlpha(float $alpha): static
    {
        return new static($this->h, $this->w, $this->b, $alpha);
    }
}
