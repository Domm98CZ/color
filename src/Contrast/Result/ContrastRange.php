<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Contrast\Result;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Compositing\LayerStack;
use Domm98CZ\Color\Contrast\Contract\ContrastAlgorithm;
use Domm98CZ\Color\Contrast\Exception\EmptyBackgroundSetException;

/**
 * The lowest and the highest contrast of a foreground against a set of possible backgrounds
 * (e.g. every color a text can be placed on). Useful when the exact background is not known but
 * the set is.
 */
final readonly class ContrastRange
{
    /**
     * @param non-empty-list<ContrastMeasurement> $measurements in the order of the backgrounds
     */
    private function __construct(
        public ContrastMeasurement $min,
        public ContrastMeasurement $max,
        public array $measurements,
    ) {}

    /**
     * @param list<Color|LayerStack> $backgrounds at least one; ties keep the first
     *
     * @throws EmptyBackgroundSetException
     */
    public static function measure(ContrastAlgorithm $algorithm, Color $foreground, array $backgrounds): self
    {
        $measurements = \array_map(static fn(Color|LayerStack $background) => $algorithm->measure($foreground, $background), $backgrounds);

        if ($measurements === []) {
            throw new EmptyBackgroundSetException('A contrast range needs at least one background.');
        }

        $min = $max = $measurements[0];
        foreach ($measurements as $measurement) {
            if ($measurement->value < $min->value) {
                $min = $measurement;
            }
            if ($measurement->value > $max->value) {
                $max = $measurement;
            }
        }

        return new self($min, $max, $measurements);
    }
}
