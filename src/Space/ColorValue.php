<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space;

use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Exception\InvalidColorValueException;

/**
 * A color as plain data: a space, its coordinates and an alpha.
 *
 * This is the exchange type of the modules below `Domm98CZ\Color\Color`: `Notation` reads and
 * writes it, `Interpolation` computes with it. `Color` is built from it (`Color::parse()`,
 * `Color::fromHex()`, `Color::fromJson()`) and converted into it for serialisation. It exists so
 * that these modules do not depend on `Color`, which would otherwise form a cycle with `Notation`.
 *
 * Coordinates are checked for arity and finiteness; normalisation (hue into [0, 360), -0.0) is
 * left to `Color`.
 */
final readonly class ColorValue
{
    /** @var list<float|null> */
    public array $coordinates;

    /**
     * @param list<float|int|null> $coordinates null = missing component (CSS `none`)
     * @param float|null $alpha 0–1, or null for a missing alpha
     *
     * @throws InvalidColorValueException
     */
    public function __construct(
        public ColorSpace $space,
        array $coordinates,
        public ?float $alpha = 1.0,
    ) {
        if (!\array_is_list($coordinates) || \count($coordinates) !== \count($space->channels())) {
            throw new InvalidColorValueException(\sprintf('Color space "%s" expects %d coordinates, %d given.', $space->id(), \count($space->channels()), \count($coordinates)));
        }

        foreach ($coordinates as $coordinate) {
            if ($coordinate !== null && !\is_finite($coordinate)) {
                throw new InvalidColorValueException('Coordinates must be finite numbers or null.');
            }
        }

        // An int is widened to float by the closure's return type.
        $this->coordinates = \array_map(static fn(float|int|null $value): ?float => $value, $coordinates);

        if ($alpha !== null && (!\is_finite($alpha) || $alpha < 0.0 || $alpha > 1.0)) {
            throw new InvalidColorValueException(\sprintf('Alpha must be a number in [0, 1] or null, %s given.', \var_export($alpha, true)));
        }
    }

    public function hasMissingComponents(): bool
    {
        return $this->alpha === null || \in_array(null, $this->coordinates, true);
    }
}
