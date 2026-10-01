<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Record;

use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Internal\Coordinates;

/**
 * OKLab (CSS `oklab()`): L 0–1, a and b roughly −0.4…0.4.
 *
 * Channels: l = lightness, a = a, b = b.
 */
final readonly class Oklab implements CoordinateRecord
{
    public float $l;
    public float $a;
    public float $b;
    public float $alpha;

    public function __construct(float $l, float $a, float $b, float $alpha = 1.0)
    {
        $this->l = Coordinates::finite($l, 'Oklab l');
        $this->a = Coordinates::finite($a, 'Oklab a');
        $this->b = Coordinates::finite($b, 'Oklab b');
        $this->alpha = Coordinates::alpha($alpha);
    }

    public static function space(): ColorSpace
    {
        return ColorSpaces::oklab();
    }

    public static function fromCoordinates(array $coordinates, float $alpha): static
    {
        [$l, $a, $b] = Coordinates::normalize(self::space(), $coordinates);

        return new static($l ?? 0.0, $a ?? 0.0, $b ?? 0.0, $alpha);
    }

    public function coordinates(): array
    {
        return [$this->l, $this->a, $this->b];
    }

    public function alpha(): float
    {
        return $this->alpha;
    }

    public function withL(float $l): static
    {
        return new static($l, $this->a, $this->b, $this->alpha);
    }

    public function withA(float $a): static
    {
        return new static($this->l, $a, $this->b, $this->alpha);
    }

    public function withB(float $b): static
    {
        return new static($this->l, $this->a, $b, $this->alpha);
    }

    public function withAlpha(float $alpha): static
    {
        return new static($this->l, $this->a, $this->b, $alpha);
    }
}
