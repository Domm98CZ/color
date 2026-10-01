<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Colorimetry;

use Domm98CZ\Color\Colorimetry\Exception\InvalidColorimetricDataException;
use Domm98CZ\Color\Math\Numeric;
use Domm98CZ\Color\Math\Vector3;

/**
 * CIE 1931 xy chromaticity coordinates (CIE 15:2018), convertible to the CIE 1976 UCS u′v′ and the
 * CIE 1960 UCS uv (u = u′, v = 2/3 v′) used for correlated color temperature.
 */
final readonly class Chromaticity
{
    public float $x;
    public float $y;

    public function __construct(float $x, float $y)
    {
        $this->x = Numeric::assertFinite($x, 'Chromaticity x');
        $this->y = Numeric::assertFinite($y, 'Chromaticity y');

        if ($y <= 0.0) {
            throw new InvalidColorimetricDataException(\sprintf('Chromaticity y must be > 0, %s given.', \var_export($y, true)));
        }
    }

    /**
     * Chromaticity of tristimulus values: x = X / (X + Y + Z), y = Y / (X + Y + Z).
     *
     * @throws InvalidColorimetricDataException when X + Y + Z ≤ 0 or Y ≤ 0 (chromaticity undefined)
     */
    public static function fromXyz(Vector3 $xyz): self
    {
        $sum = $xyz->x + $xyz->y + $xyz->z;

        if ($sum <= 0.0) {
            throw new InvalidColorimetricDataException('Chromaticity is undefined for X + Y + Z <= 0.');
        }

        return new self($xyz->x / $sum, $xyz->y / $sum);
    }

    /**
     * Tristimulus values with the given luminance Y: X = xY/y, Z = (1 − x − y)Y/y.
     */
    public function toXyz(float $luminance = 1.0): Vector3
    {
        return new Vector3(
            $this->x * $luminance / $this->y,
            $luminance,
            (1.0 - $this->x - $this->y) * $luminance / $this->y,
        );
    }

    /**
     * CIE 1976 UCS: u′ = 4x / (−2x + 12y + 3), v′ = 9y / (−2x + 12y + 3).
     *
     * @return array{0: float, 1: float} [u′, v′]
     */
    public function toUv(): array
    {
        $denominator = -2.0 * $this->x + 12.0 * $this->y + 3.0;

        return [4.0 * $this->x / $denominator, 9.0 * $this->y / $denominator];
    }

    /**
     * Inverse of {@see toUv()}: x = 9u′ / (6u′ − 16v′ + 12), y = 4v′ / (6u′ − 16v′ + 12).
     *
     * @throws InvalidColorimetricDataException when the result is not a valid chromaticity
     */
    public static function fromUv(float $u, float $v): self
    {
        $denominator = 6.0 * $u - 16.0 * $v + 12.0;

        return new self(9.0 * $u / $denominator, 4.0 * $v / $denominator);
    }
}
