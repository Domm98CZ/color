<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Difference\Algorithm;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Difference\Contract\ColorDifferenceMetric;
use Domm98CZ\Color\Difference\Exception\InvalidMetricParameterException;
use Domm98CZ\Color\Difference\Internal\LabMetric;
use Domm98CZ\Color\Difference\Internal\Pair;
use Domm98CZ\Color\Difference\Internal\Sources;
use Domm98CZ\Color\Difference\Result\ColorDifference;
use Domm98CZ\Color\Difference\Result\DifferenceComponents;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Space\Definition\LabSpace;

/**
 * ΔE00 (CIE 142-2001), implemented step by step as in Sharma, Wu & Dalal (2005), equations 1–22,
 * including their conventions for neutral colors (h′ = 0 when a′ = b = 0) and for hue differences
 * of exactly ±180°. Symmetric. Default parametric factors kL = kC = kH = 1.
 */
final readonly class Ciede2000 implements ColorDifferenceMetric
{
    /** 25⁷ */
    private const float POW25_7 = 6103515625.0;

    private LabSpace $lab;
    private float $kL;
    private float $kC;
    private float $kH;

    public function __construct(float $kL = 1.0, float $kC = 1.0, float $kH = 1.0, ?LabSpace $lab = null)
    {
        $this->kL = InvalidMetricParameterException::check('kL', $kL);
        $this->kC = InvalidMetricParameterException::check('kC', $kC);
        $this->kH = InvalidMetricParameterException::check('kH', $kH);
        $this->lab = LabMetric::space($lab);
    }

    public function id(): AlgorithmId
    {
        return new AlgorithmId(
            'ciede2000',
            1,
            [Sources::cie142(), Sources::sharma2005()],
            \sprintf('kL=%s,kC=%s,kH=%s,%s', $this->kL, $this->kC, $this->kH, LabMetric::variant($this->lab)),
        );
    }

    public function difference(Color $reference, Color $sample): ColorDifference
    {
        $pair = Pair::in($this->lab, $reference, $sample);
        [$l1, $a1, $b1] = $pair->reference;
        [$l2, $a2, $b2] = $pair->sample;

        // (2)–(7): a′, C′, h′
        $cMean = (\hypot($a1, $b1) + \hypot($a2, $b2)) / 2.0;
        $g = 0.5 * (1.0 - \sqrt($cMean ** 7 / ($cMean ** 7 + self::POW25_7)));
        $a1p = (1.0 + $g) * $a1;
        $a2p = (1.0 + $g) * $a2;
        $c1p = \hypot($a1p, $b1);
        $c2p = \hypot($a2p, $b2);
        $h1p = self::hueAngle($a1p, $b1);
        $h2p = self::hueAngle($a2p, $b2);

        // (8)–(11): ΔL′, ΔC′, Δh′, ΔH′
        $deltaL = $l2 - $l1;
        $deltaC = $c2p - $c1p;
        $chromaProduct = $c1p * $c2p;
        $deltaHue = 0.0;
        if ($chromaProduct !== 0.0) {
            $deltaHue = $h2p - $h1p;
            if ($deltaHue > 180.0) {
                $deltaHue -= 360.0;
            } elseif ($deltaHue < -180.0) {
                $deltaHue += 360.0;
            }
        }
        $deltaH = 2.0 * \sqrt($chromaProduct) * \sin(\deg2rad($deltaHue / 2.0));

        // (12)–(14): L̄′, C̄′, h̄′
        $lMean = ($l1 + $l2) / 2.0;
        $cMeanP = ($c1p + $c2p) / 2.0;
        $hMean = $h1p + $h2p;
        if ($chromaProduct !== 0.0) {
            if (\abs($h1p - $h2p) <= 180.0) {
                $hMean /= 2.0;
            } elseif ($hMean < 360.0) {
                $hMean = ($hMean + 360.0) / 2.0;
            } else {
                $hMean = ($hMean - 360.0) / 2.0;
            }
        }

        // (15)–(22)
        $t = 1.0
            - 0.17 * \cos(\deg2rad($hMean - 30.0))
            + 0.24 * \cos(\deg2rad(2.0 * $hMean))
            + 0.32 * \cos(\deg2rad(3.0 * $hMean + 6.0))
            - 0.20 * \cos(\deg2rad(4.0 * $hMean - 63.0));
        $deltaTheta = 30.0 * \exp(-((($hMean - 275.0) / 25.0) ** 2));
        $rc = 2.0 * \sqrt($cMeanP ** 7 / ($cMeanP ** 7 + self::POW25_7));
        $sl = 1.0 + 0.015 * ($lMean - 50.0) ** 2 / \sqrt(20.0 + ($lMean - 50.0) ** 2);
        $sc = 1.0 + 0.045 * $cMeanP;
        $sh = 1.0 + 0.015 * $cMeanP * $t;
        $rt = -\sin(\deg2rad(2.0 * $deltaTheta)) * $rc;

        $lightness = $deltaL / ($this->kL * $sl);
        $chroma = $deltaC / ($this->kC * $sc);
        $hue = $deltaH / ($this->kH * $sh);

        $value = \sqrt($lightness ** 2 + $chroma ** 2 + $hue ** 2 + $rt * $chroma * $hue);

        return new ColorDifference($value, $this->id(), $pair->diagnostics, new DifferenceComponents($deltaL, $deltaC, $deltaH));
    }

    /**
     * h′ in [0, 360); 0 when a′ = b = 0 (Sharma et al., eq. 7).
     */
    private static function hueAngle(float $a, float $b): float
    {
        if ($a === 0.0 && $b === 0.0) {
            return 0.0;
        }

        $hue = \rad2deg(\atan2($b, $a));

        return $hue < 0.0 ? $hue + 360.0 : $hue;
    }
}
