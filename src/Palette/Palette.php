<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Palette\Exception\InvalidPaletteException;

/**
 * An ordered, immutable list of colors, each optionally named; names are unique.
 */
final readonly class Palette implements \Countable
{
    /**
     * @param list<PaletteEntry> $entries
     */
    private function __construct(private array $entries)
    {
        $names = [];
        foreach ($entries as $entry) {
            if ($entry->name !== null && \trim($entry->name) === '') {
                throw new InvalidPaletteException('Palette names must not be empty.');
            }

            if ($entry->name !== null && \in_array($entry->name, $names, true)) {
                throw new InvalidPaletteException(\sprintf('The palette name "%s" is used twice.', $entry->name));
            }

            $names[] = $entry->name;
        }
    }

    public static function of(Color ...$colors): self
    {
        return new self(\array_values(\array_map(static fn(Color $color): PaletteEntry => new PaletteEntry($color), $colors)));
    }

    /**
     * @param array<non-empty-string, Color> $colors name => color, in order
     *
     * @throws InvalidPaletteException
     */
    public static function named(array $colors): self
    {
        $entries = [];
        foreach ($colors as $name => $color) {
            $entries[] = new PaletteEntry($color, (string) $name);
        }

        return new self($entries);
    }

    /**
     * @param list<PaletteEntry> $entries
     *
     * @throws InvalidPaletteException when a name is empty or used twice
     */
    public static function fromEntries(array $entries): self
    {
        return new self($entries);
    }

    /**
     * @return list<PaletteEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<Color>
     */
    public function colors(): array
    {
        return \array_map(static fn(PaletteEntry $entry): Color => $entry->color, $this->entries);
    }

    public function count(): int
    {
        return \count($this->entries);
    }

    /**
     * The color at a position (0-based) or with a name.
     *
     * @throws InvalidPaletteException when there is no such entry
     */
    public function get(int|string $key): Color
    {
        return $this->entries[$this->indexOf($key)]->color;
    }

    /**
     * The position (0-based) of the entry at a position or with a name.
     *
     * @throws InvalidPaletteException when there is no such entry
     */
    public function indexOf(int|string $key): int
    {
        foreach ($this->entries as $index => $entry) {
            if ($key === $index || $key === $entry->name) {
                return $index;
            }
        }

        throw new InvalidPaletteException(\sprintf('The palette has no entry %s.', \is_int($key) ? '#' . $key : '"' . $key . '"'));
    }

    /**
     * A new palette with $color appended.
     *
     * @param non-empty-string|null $name
     *
     * @throws InvalidPaletteException when the name is already used
     */
    public function with(Color $color, ?string $name = null): self
    {
        return new self([...$this->entries, new PaletteEntry($color, $name)]);
    }

    /**
     * The same colors with these names, in order (e.g. ['50', '100', …, '900']).
     *
     * @param list<non-empty-string> $names exactly one per entry
     *
     * @throws InvalidPaletteException
     */
    public function withNames(array $names): self
    {
        if (\count($names) !== \count($this->entries)) {
            throw new InvalidPaletteException(\sprintf('%d names given for %d colors.', \count($names), \count($this->entries)));
        }

        return new self(\array_map(static fn(PaletteEntry $entry, string $name): PaletteEntry => new PaletteEntry($entry->color, $name), $this->entries, $names));
    }
}
