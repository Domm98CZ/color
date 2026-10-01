<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Record;

use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Internal\Coordinates;

/**
 * CIE LCh relative to D50 (CSS `lch()`): L* 0–100, chroma ≥ 0, hue in degrees (null when powerless).
 *
 * Channels: l = lightness, c = chroma, h = hue.
 */
final readonly class Lch implements CoordinateRecord
{
    public float $l;
    public float $c;
    public ?float $h;
    public float $alpha;

    public function __construct(float $l, float $c, ?float $h, float $alpha = 1.0)
    {
        $this->l = Coordinates::finite($l, 'Lch l');
        $this->c = Coordinates::finite($c, 'Lch c');
        $this->h = Coordinates::hue($h);
        $this->alpha = Coordinates::alpha($alpha);
    }

    public static function space(): ColorSpace
    {
        return ColorSpaces::lch();
    }

    public static function fromCoordinates(array $coordinates, float $alpha): static
    {
        [$l, $c, $h] = Coordinates::normalize(self::space(), $coordinates);

        return new static($l ?? 0.0, $c ?? 0.0, $h, $alpha);
    }

    public function coordinates(): array
    {
        return [$this->l, $this->c, $this->h];
    }

    public function alpha(): float
    {
        return $this->alpha;
    }

    public function withL(float $l): static
    {
        return new static($l, $this->c, $this->h, $this->alpha);
    }

    public function withC(float $c): static
    {
        return new static($this->l, $c, $this->h, $this->alpha);
    }

    public function withH(?float $h): static
    {
        return new static($this->l, $this->c, $h, $this->alpha);
    }

    public function withAlpha(float $alpha): static
    {
        return new static($this->l, $this->c, $this->h, $alpha);
    }
}
