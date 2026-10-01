<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Record;

use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Internal\Coordinates;

/**
 * Display P3 components on the 0–1 scale (CSS `color(display-p3 r g b)`).
 *
 * Channels: r = red, g = green, b = blue.
 */
final readonly class DisplayP3 implements CoordinateRecord
{
    public float $r;
    public float $g;
    public float $b;
    public float $alpha;

    public function __construct(float $r, float $g, float $b, float $alpha = 1.0)
    {
        $this->r = Coordinates::finite($r, 'DisplayP3 r');
        $this->g = Coordinates::finite($g, 'DisplayP3 g');
        $this->b = Coordinates::finite($b, 'DisplayP3 b');
        $this->alpha = Coordinates::alpha($alpha);
    }

    public static function space(): ColorSpace
    {
        return ColorSpaces::displayP3();
    }

    public static function fromCoordinates(array $coordinates, float $alpha): static
    {
        [$r, $g, $b] = Coordinates::normalize(self::space(), $coordinates);

        return new static($r ?? 0.0, $g ?? 0.0, $b ?? 0.0, $alpha);
    }

    public function coordinates(): array
    {
        return [$this->r, $this->g, $this->b];
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
