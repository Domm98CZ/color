<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Difference\Internal;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Provenance\Diagnostics;
use Domm98CZ\Color\Provenance\Warning;
use Domm98CZ\Color\Provenance\WarningCode;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * @internal two colors converted into the space a metric works in
 */
final readonly class Pair
{
    /**
     * @param array{float, float, float} $reference
     * @param array{float, float, float} $sample
     */
    private function __construct(
        public array $reference,
        public array $sample,
        public Diagnostics $diagnostics,
    ) {}

    /**
     * Converts both colors (missing components count as 0, CSS Color 4 § 4.4). Color-difference
     * formulas ignore alpha; a translucent input is reported with an `alpha-ignored` warning.
     */
    public static function in(ColorSpace $space, Color $reference, Color $sample): self
    {
        $diagnostics = Diagnostics::none();

        if ($reference->alpha() !== 1.0 || $sample->alpha() !== 1.0) {
            $diagnostics = $diagnostics->withWarning(new Warning(
                WarningCode::AlphaIgnored,
                'Color-difference formulas compare opaque colors; the alpha of the input was ignored. Composite translucent colors over their backdrop first.',
                ['referenceAlpha' => $reference->alpha(), 'sampleAlpha' => $sample->alpha()],
            ));
        }

        return new self(self::coordinates($reference->to($space)), self::coordinates($sample->to($space)), $diagnostics);
    }

    /**
     * @return array{float, float, float}
     */
    private static function coordinates(Color $color): array
    {
        [$first, $second, $third] = $color->coordinates();

        return [$first ?? 0.0, $second ?? 0.0, $third ?? 0.0];
    }
}
