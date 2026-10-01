<?php

declare(strict_types=1);

// A color outside a gamut is a valid value, not an error: Display P3 green is kept as it is and
// isInGamut() tells you it does not fit sRGB. Lab and OKLab are unbounded, so every color fits there.
// Run: php examples/gamut.php

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Space\ColorSpaces;

require __DIR__ . '/../vendor/autoload.php';

// Pure Display P3 green is outside sRGB.
$p3Green = Color::of(ColorSpaces::displayP3(), [0.0, 1.0, 0.0]);
$inSrgb = $p3Green->to(ColorSpaces::srgb());

vprintf("P3 green in srgb: %.6f %.6f %.6f\n", $inSrgb->coordinates());
printf("in display-p3 gamut: %s\n", $p3Green->isInGamut() ? 'yes' : 'no');
printf("in srgb gamut:       %s\n", $p3Green->isInGamut(ColorSpaces::srgb()) ? 'yes' : 'no');

// Lab and OKLab are unbounded, so every color is "in gamut" there.
printf("in oklab gamut:      %s\n", $p3Green->isInGamut(ColorSpaces::oklab()) ? 'yes' : 'no');
