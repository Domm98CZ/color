<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette\Result;

use Domm98CZ\Color\Contrast\Contract\ContrastAlgorithm;
use Domm98CZ\Color\Palette\Palette;
use Domm98CZ\Color\Provenance\AlgorithmId;

/**
 * Pairwise differences of a palette by one metric.
 */
final readonly class PaletteAnalysis
{
    /**
     * @param list<list<float>> $differences [i][j] = difference(reference i, sample j); 0 on the diagonal
     * @param list<PairDifference> $nearest for every entry, its closest other entry (empty for one color)
     */
    public function __construct(
        public Palette $palette,
        public AlgorithmId $metric,
        public array $differences,
        public ?PairDifference $minDifference,
        public ?PairDifference $maxDifference,
        public array $nearest,
    ) {}

    /**
     * [i][j] = contrast of entry i as foreground on entry j as background (e.g. WCAG 2 ratio).
     * The algorithm's rules apply (WCAG 2 needs opaque sRGB colors).
     *
     * @return list<list<float>>
     */
    public function contrastMatrix(ContrastAlgorithm $algorithm): array
    {
        $colors = $this->palette->colors();

        return \array_map(
            static fn($foreground): array => \array_map(static fn($background): float => $algorithm->measure($foreground, $background)->value, $colors),
            $colors,
        );
    }
}
