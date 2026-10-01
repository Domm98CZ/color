<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Harmony\Contract;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Harmony\Result\WheelClassification;
use Domm98CZ\Color\Harmony\WheelPosition;

/**
 * A wheel with primary colors: its twelve positions (every 30°) are primaries, secondaries and
 * tertiaries, and a color can be classified by the nearest one. Perceptual wheels (OKLCh, CIELCh)
 * have no primaries and do not classify.
 */
interface ClassifyingHueWheel extends HueWheel
{
    /**
     * @return list<WheelPosition> the twelve positions, from 0° in steps of 30°
     */
    public function positions(): array;

    public function classify(Color $color): WheelClassification;
}
