<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Provenance;

/**
 * What kind of authority a source is. Keeps standards, papers, design-system recommendations
 * and library heuristics clearly apart.
 */
enum SourceKind: string
{
    /** A formal standard (ISO, IEC, CIE, ITU, W3C Recommendation…). */
    case Standard = 'standard';

    /** A specification that is not (yet) a formal standard, e.g. a W3C Candidate Recommendation Draft. */
    case Specification = 'specification';

    /** A peer-reviewed or otherwise citable publication. */
    case Paper = 'paper';

    /** A recommendation of a specific design system or tool; never a universal standard. */
    case DesignSystem = 'design-system';

    /** A heuristic defined and documented by this library. */
    case Library = 'library-heuristic';

    /** A rule supplied by the library user (project or customer rule). */
    case UserDefined = 'user-defined';
}
