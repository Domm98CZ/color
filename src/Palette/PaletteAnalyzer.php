<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette;

use Domm98CZ\Color\Difference\Contract\ColorDifferenceMetric;
use Domm98CZ\Color\Palette\Result\PairDifference;
use Domm98CZ\Color\Palette\Result\PaletteAnalysis;

/**
 * Analyses a palette with an explicit ΔE metric: the full difference matrix (both orders, for
 * asymmetric metrics), the closest and the farthest pair (i < j, the earlier entry as reference;
 * the first such pair wins ties) and each entry's closest neighbour.
 */
final readonly class PaletteAnalyzer
{
    public function __construct(private ColorDifferenceMetric $metric) {}

    public function analyze(Palette $palette): PaletteAnalysis
    {
        $colors = $palette->colors();
        $matrix = [];
        $pairs = [];

        foreach ($colors as $i => $reference) {
            $row = [];
            foreach ($colors as $j => $sample) {
                if ($i === $j) {
                    $row[] = 0.0;
                    continue;
                }

                $difference = $this->metric->difference($reference, $sample);
                $row[] = $difference->value;
                $pairs[$i][$j] = new PairDifference($i, $j, $difference);
            }
            $matrix[] = $row;
        }

        $min = null;
        $max = null;
        $nearest = [];

        foreach ($pairs as $i => $row) {
            foreach ($row as $j => $pair) {
                if ($j > $i) {
                    $min = $min === null || $pair->difference->value < $min->difference->value ? $pair : $min;
                    $max = $max === null || $pair->difference->value > $max->difference->value ? $pair : $max;
                }
            }

            // usort is stable: on equal differences the earlier neighbour wins.
            $candidates = $row;
            \usort($candidates, static fn(PairDifference $a, PairDifference $b): int => $a->difference->value <=> $b->difference->value);
            $nearest[] = $candidates[0];
        }

        return new PaletteAnalysis($palette, $this->metric->id(), $matrix, $min, $max, $nearest);
    }
}
