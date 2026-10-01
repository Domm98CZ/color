<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space;

use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Exception\DuplicateColorSpaceException;
use Domm98CZ\Color\Space\Exception\UnknownColorSpaceException;

/**
 * Immutable map of color space ids to spaces, used where a space must be found by its id
 * (parsing, deserialisation). There is no global mutable registry: extend a registry with
 * {@see with()} and pass it where it is needed.
 */
final readonly class ColorSpaceRegistry
{
    /**
     * @param array<string, ColorSpace> $spaces
     */
    private function __construct(private array $spaces) {}

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Registry with all built-in spaces ({@see ColorSpaces::all()}).
     */
    public static function default(): self
    {
        $registry = self::empty();

        foreach (ColorSpaces::all() as $space) {
            $registry = $registry->with($space);
        }

        return $registry;
    }

    /**
     * @throws DuplicateColorSpaceException
     */
    public function with(ColorSpace $space): self
    {
        if (isset($this->spaces[$space->id()])) {
            throw DuplicateColorSpaceException::forId($space->id());
        }

        return new self([...$this->spaces, $space->id() => $space]);
    }

    public function has(string $id): bool
    {
        return isset($this->spaces[$id]);
    }

    /**
     * @throws UnknownColorSpaceException
     */
    public function get(string $id): ColorSpace
    {
        return $this->spaces[$id] ?? throw UnknownColorSpaceException::forId($id);
    }

    /**
     * @return list<ColorSpace> in registration order
     */
    public function all(): array
    {
        return \array_values($this->spaces);
    }
}
