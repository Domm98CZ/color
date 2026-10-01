<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Record;

use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Internal\Coordinates;

/**
 * HSV/HSB over sRGB: hue in degrees (null when powerless), saturation and value 0–100.
 *
 * Channels: h = hue, s = saturation, v = value.
 */
final readonly class Hsv implements CoordinateRecord
{
    public ?float $h;
    public float $s;
    public float $v;
    public float $alpha;

    public function __construct(?float $h, float $s, float $v, float $alpha = 1.0)
    {
        $this->h = Coordinates::hue($h);
        $this->s = Coordinates::finite($s, 'Hsv s');
        $this->v = Coordinates::finite($v, 'Hsv v');
        $this->alpha = Coordinates::alpha($alpha);
    }

    public static function space(): ColorSpace
    {
        return ColorSpaces::hsv();
    }

    public static function fromCoordinates(array $coordinates, float $alpha): static
    {
        [$h, $s, $v] = Coordinates::normalize(self::space(), $coordinates);

        return new static($h, $s ?? 0.0, $v ?? 0.0, $alpha);
    }

    public function coordinates(): array
    {
        return [$this->h, $this->s, $this->v];
    }

    public function alpha(): float
    {
        return $this->alpha;
    }

    public function withH(?float $h): static
    {
        return new static($h, $this->s, $this->v, $this->alpha);
    }

    public function withS(float $s): static
    {
        return new static($this->h, $s, $this->v, $this->alpha);
    }

    public function withV(float $v): static
    {
        return new static($this->h, $this->s, $v, $this->alpha);
    }

    public function withAlpha(float $alpha): static
    {
        return new static($this->h, $this->s, $this->v, $alpha);
    }
}
