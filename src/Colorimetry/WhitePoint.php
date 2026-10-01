<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Colorimetry;

use Domm98CZ\Color\Colorimetry\Exception\InvalidColorimetricDataException;
use Domm98CZ\Color\Math\Vector3;

/**
 * Reference white of a color space, as tristimulus values normalised to Y = 1.
 *
 * Two white points are the same only when their XYZ values are identical; differently rounded
 * definitions of "the same" illuminant are deliberately treated as different whites.
 */
final readonly class WhitePoint
{
    /**
     * @param non-empty-string $name
     */
    private function __construct(
        public string $name,
        public Vector3 $xyz,
        public StandardObserver $observer,
    ) {}

    /**
     * @param non-empty-string $name
     */
    public static function fromChromaticity(string $name, Chromaticity $chromaticity, StandardObserver $observer = StandardObserver::Cie1931TwoDegree): self
    {
        return self::create($name, $chromaticity->toXyz(1.0), $observer);
    }

    /**
     * @param non-empty-string $name
     */
    public static function fromXyz(string $name, Vector3 $xyz, StandardObserver $observer = StandardObserver::Cie1931TwoDegree): self
    {
        if ($xyz->y !== 1.0) {
            throw new InvalidColorimetricDataException(\sprintf('A white point must be normalised to Y = 1, Y = %s given.', \var_export($xyz->y, true)));
        }

        return self::create($name, $xyz, $observer);
    }

    public function chromaticity(): Chromaticity
    {
        return Chromaticity::fromXyz($this->xyz);
    }

    public function equals(self $other): bool
    {
        return $this->observer === $other->observer
            && $this->xyz->x === $other->xyz->x
            && $this->xyz->y === $other->xyz->y
            && $this->xyz->z === $other->xyz->z;
    }

    private static function create(string $name, Vector3 $xyz, StandardObserver $observer): self
    {
        if (\trim($name) === '') {
            throw new InvalidColorimetricDataException('A white point name must not be empty.');
        }

        if ($xyz->x <= 0.0 || $xyz->z <= 0.0) {
            throw new InvalidColorimetricDataException('A white point must have positive X and Z.');
        }

        return new self($name, $xyz, $observer);
    }
}
