<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Record;

use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Internal\Coordinates;

/**
 * HSL over sRGB: hue in degrees (null when powerless), saturation and lightness 0–100 as in CSS `hsl()`.
 *
 * Channels: h = hue, s = saturation, l = lightness.
 */
final readonly class Hsl implements CoordinateRecord
{
    public ?float $h;
    public float $s;
    public float $l;
    public float $alpha;

    public function __construct(?float $h, float $s, float $l, float $alpha = 1.0)
    {
        $this->h = Coordinates::hue($h);
        $this->s = Coordinates::finite($s, 'Hsl s');
        $this->l = Coordinates::finite($l, 'Hsl l');
        $this->alpha = Coordinates::alpha($alpha);
    }

    public static function space(): ColorSpace
    {
        return ColorSpaces::hsl();
    }

    public static function fromCoordinates(array $coordinates, float $alpha): static
    {
        [$h, $s, $l] = Coordinates::normalize(self::space(), $coordinates);

        return new static($h, $s ?? 0.0, $l ?? 0.0, $alpha);
    }

    public function coordinates(): array
    {
        return [$this->h, $this->s, $this->l];
    }

    public function alpha(): float
    {
        return $this->alpha;
    }

    public function withH(?float $h): static
    {
        return new static($h, $this->s, $this->l, $this->alpha);
    }

    public function withS(float $s): static
    {
        return new static($this->h, $s, $this->l, $this->alpha);
    }

    public function withL(float $l): static
    {
        return new static($this->h, $this->s, $l, $this->alpha);
    }

    public function withAlpha(float $alpha): static
    {
        return new static($this->h, $this->s, $this->l, $alpha);
    }
}
