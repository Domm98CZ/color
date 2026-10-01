<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Colorimetry\Algorithm;

use Domm98CZ\Color\Colorimetry\Contract\ChromaticAdaptationTransform;
use Domm98CZ\Color\Colorimetry\Internal\ConeSpaceScaling;
use Domm98CZ\Color\Colorimetry\WhitePoint;
use Domm98CZ\Color\Math\Matrix3;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Provenance\SourceKind;
use Domm98CZ\Color\Provenance\SourceReference;

/**
 * Linear Bradford chromatic adaptation (von Kries scaling in the Bradford "sharpened" cone space).
 *
 * This is the simplified, linear form used by ICC.1 and CSS Color 4; the original Bradford transform
 * (Lam 1985) has an additional non-linearity in the blue channel which is intentionally not applied.
 *
 * A = M⁻¹ · diag(M·W_to / M·W_from) · M
 */
final class Bradford implements ChromaticAdaptationTransform
{
    /**
     * Bradford cone response matrix (Lam 1985; ICC.1 Annex E).
     */
    private const array CONE_RESPONSE = [
        [0.8951, 0.2664, -0.1614],
        [-0.7502, 1.7135, 0.0367],
        [0.0389, -0.0685, 1.0296],
    ];

    public function id(): AlgorithmId
    {
        return new AlgorithmId('bradford', revision: 1, variant: 'linear', sources: [
            new SourceReference('CSS Color Module Level 4', SourceKind::Specification, 'W3C Candidate Recommendation Draft, 26 September 2026', 'Sample code for color conversions', 'https://www.w3.org/TR/css-color-4/'),
            new SourceReference('ICC.1 Image technology colour management — Architecture, profile format, and data structure', SourceKind::Standard, 'ICC.1:2022 (v4.4)', 'Annex E'),
            new SourceReference('K. M. Lam: Metamerism and colour constancy (PhD thesis, University of Bradford)', SourceKind::Paper, '1985'),
        ]);
    }

    public function matrix(WhitePoint $from, WhitePoint $to): Matrix3
    {
        return ConeSpaceScaling::matrix(self::CONE_RESPONSE, $from, $to);
    }
}
