<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Provenance;

/**
 * Stable codes of structured warnings. The values are part of the public API.
 */
enum WarningCode: string
{
    case AlphaIgnored = 'alpha-ignored';
    case HueUndefined = 'hue-undefined';

    /**
     * Short generic explanation of the code; a concrete {@see Warning} carries a specific message.
     */
    public function description(): string
    {
        return match ($this) {
            self::AlphaIgnored => 'The alpha channel was not used by the computation.',
            self::HueUndefined => 'The hue is undefined (powerless) for an achromatic color.',
        };
    }
}
