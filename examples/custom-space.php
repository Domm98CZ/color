<?php

declare(strict_types=1);

// A user-defined RGB space from primaries, a white point and a transfer function (here sRGB
// primaries with a plain 2.2 gamma). The registry is immutable: with() returns an extended copy
// and leaves the default registry untouched.
// Run: php examples/custom-space.php

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Colorimetry\Algorithm\GammaTransferFunction;
use Domm98CZ\Color\Colorimetry\Chromaticity;
use Domm98CZ\Color\Colorimetry\RgbPrimaries;
use Domm98CZ\Color\Space\ColorSpaceRegistry;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Definition\RgbSpace;

require __DIR__ . '/../vendor/autoload.php';

// sRGB primaries with a plain gamma 2.2 instead of the piecewise sRGB curve.
$space = RgbSpace::fromPrimaries(
    'example-srgb-gamma22',
    'sRGB primaries, gamma 2.2',
    new RgbPrimaries(new Chromaticity(0.64, 0.33), new Chromaticity(0.30, 0.60), new Chromaticity(0.15, 0.06)),
    ColorSpaces::xyzD65(),
    new GammaTransferFunction(2.2),
);

// The registry is immutable: with() returns an extended copy.
$registry = ColorSpaceRegistry::default()->with($space);
printf("registered: %s\n", $registry->has('example-srgb-gamma22') ? 'yes' : 'no');

$mid = Color::of($space, [0.5, 0.5, 0.5]);
vprintf("as srgb: %.6f %.6f %.6f\n", $mid->to(ColorSpaces::srgb())->coordinates());
