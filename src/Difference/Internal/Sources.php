<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Difference\Internal;

use Domm98CZ\Color\Provenance\SourceKind;
use Domm98CZ\Color\Provenance\SourceReference;

/**
 * @internal source citations shared by the color-difference metrics
 */
final class Sources
{
    private function __construct() {}

    public static function cie15(): SourceReference
    {
        return new SourceReference('CIE 15 Colorimetry', SourceKind::Standard, 'CIE 15:2018, 4th edition', 'CIELAB colour difference');
    }

    public static function cie116(): SourceReference
    {
        return new SourceReference('CIE 116 Industrial colour-difference evaluation', SourceKind::Standard, 'CIE 116-1995');
    }

    public static function iso105J03(): SourceReference
    {
        return new SourceReference('ISO 105-J03 Textiles — Tests for colour fastness — Calculation of colour differences', SourceKind::Standard, 'ISO 105-J03:2009');
    }

    public static function cie142(): SourceReference
    {
        return new SourceReference('CIE 142 Improvement to industrial colour-difference evaluation', SourceKind::Standard, 'CIE 142-2001');
    }

    public static function sharma2005(): SourceReference
    {
        return new SourceReference(
            'G. Sharma, W. Wu, E. N. Dalal: The CIEDE2000 color-difference formula: Implementation notes, supplementary test data, and mathematical observations',
            SourceKind::Paper,
            'Color Research & Application 30(1), 2005, pp. 21–30, doi:10.1002/col.20070',
            null,
            'https://hajim.rochester.edu/ece/sites/gsharma/ciede2000/',
        );
    }

    /**
     * @param non-empty-string $section
     */
    public static function cssColor4(string $section): SourceReference
    {
        return new SourceReference('CSS Color Module Level 4', SourceKind::Specification, 'W3C Candidate Recommendation Draft, 26 September 2026', $section, 'https://www.w3.org/TR/css-color-4/');
    }
}
