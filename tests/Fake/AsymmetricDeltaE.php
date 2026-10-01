<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Fake;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Difference\Contract\ColorDifferenceMetric;
use Domm98CZ\Color\Difference\Result\ColorDifference;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Provenance\Diagnostics;
use Domm98CZ\Color\Space\ColorSpaces;

/**
 * An asymmetric color-difference metric: the OKLab distance weighted by the hue of the reference.
 * No built-in metric is asymmetric, but the contract allows it, so the palette analysis keeps
 * both orders; this fake exercises that path.
 */
final class AsymmetricDeltaE implements ColorDifferenceMetric
{
    public function id(): AlgorithmId
    {
        return new AlgorithmId('fake-asymmetric-deltae');
    }

    public function difference(Color $reference, Color $sample): ColorDifference
    {
        [$l1, $a1, $b1] = $reference->to(ColorSpaces::oklab())->coordinates();
        [$l2, $a2, $b2] = $sample->to(ColorSpaces::oklab())->coordinates();
        $distance = \sqrt((($l2 ?? 0.0) - ($l1 ?? 0.0)) ** 2 + (($a2 ?? 0.0) - ($a1 ?? 0.0)) ** 2 + (($b2 ?? 0.0) - ($b1 ?? 0.0)) ** 2);
        $referenceHue = $reference->to(ColorSpaces::oklch())->coordinate('h') ?? 0.0;

        return new ColorDifference($distance * (1.0 + $referenceHue / 360.0), $this->id(), Diagnostics::none());
    }
}
