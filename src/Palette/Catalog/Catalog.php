<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette\Catalog;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Difference\Contract\ColorDifferenceMetric;
use Domm98CZ\Color\Palette\Contract\ColorCatalog;
use Domm98CZ\Color\Palette\Exception\InvalidPaletteException;

/**
 * A catalog held in memory, with nearest-color search by an explicit ΔE metric.
 *
 * The searched color is the reference of the metric (it matters for asymmetric metrics such as
 * CIE94 and CMC). On equal differences the entry that comes first in the catalog wins.
 */
final readonly class Catalog implements ColorCatalog
{
    /**
     * @param non-empty-string $id
     * @param non-empty-list<CatalogEntry> $entries
     */
    private function __construct(
        private string $id,
        private array $entries,
    ) {}

    /**
     * @param non-empty-string $id
     * @param array<non-empty-string, Color> $colors name => color
     *
     * @throws InvalidPaletteException when the catalog is empty
     */
    public static function of(string $id, array $colors): self
    {
        if ($colors === []) {
            throw new InvalidPaletteException('A catalog needs at least one color.');
        }

        $entries = [];
        foreach ($colors as $name => $color) {
            $entries[] = new CatalogEntry((string) $name, $color);
        }

        return new self($id, $entries);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function entries(): array
    {
        return $this->entries;
    }

    public function nearest(Color $color, ColorDifferenceMetric $metric): NearestMatch
    {
        $best = null;
        foreach ($this->entries as $entry) {
            $match = new NearestMatch($entry, $metric->difference($color, $entry->color));
            if ($best === null || $match->difference->value < $best->difference->value) {
                $best = $match;
            }
        }

        return $best;
    }

    /**
     * The $limit closest entries, closest first.
     *
     * @return non-empty-list<NearestMatch>
     *
     * @throws InvalidPaletteException when the limit is below 1
     */
    public function rank(Color $color, ColorDifferenceMetric $metric, int $limit = 5): array
    {
        if ($limit < 1) {
            throw new InvalidPaletteException(\sprintf('The number of matches must be at least 1, %d given.', $limit));
        }

        $matches = \array_map(static fn(CatalogEntry $entry): NearestMatch => new NearestMatch($entry, $metric->difference($color, $entry->color)), $this->entries);
        // usort is stable: equal differences keep the catalog order.
        \usort($matches, static fn(NearestMatch $a, NearestMatch $b): int => $a->difference->value <=> $b->difference->value);

        return \array_slice($matches, 0, $limit);
    }
}
