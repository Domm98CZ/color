<?php

declare(strict_types=1);

// Build a color from hex, rgb or hsl and read it in other spaces. The scales match CSS (rgb 0-255,
// hsl 0-100), nothing is clipped or rounded, and a round trip returns the same color within
// floating-point error; D65/D50 spaces are bridged by Bradford adaptation automatically.
// Run: php examples/create-and-convert.php

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Record\Hsl;
use Domm98CZ\Color\Space\Record\Lab;
use Domm98CZ\Color\Space\Record\Oklch;

require __DIR__ . '/../vendor/autoload.php';

// Named constructors use the same scales as CSS: rgb() 0–255, hsl() saturation/lightness 0–100.
$blue = Color::fromRgb(51, 102, 204);

// Typed, read-only views of the same color in other spaces. Nothing is clipped or rounded.
$oklch = $blue->in(Oklch::class);
$hsl = $blue->in(Hsl::class);
$lab = $blue->in(Lab::class);   // CIE Lab relative to D50, as CSS lab()

printf("oklch: L=%.4f C=%.4f h=%.2f\n", $oklch->l, $oklch->c, $oklch->h ?? NAN);
printf("hsl:   h=%.2f s=%.2f l=%.2f\n", $hsl->h ?? NAN, $hsl->s, $hsl->l);
printf("lab:   L=%.4f a=%.4f b=%.4f\n", $lab->l, $lab->a, $lab->b);

// to() returns a Color in another space; the conversion path goes through XYZ and applies
// Bradford chromatic adaptation between D65 and D50 where needed.
$p3 = $blue->to(ColorSpaces::displayP3());
vprintf("display-p3: %.6f %.6f %.6f\n", $p3->coordinates());

// Converting back gives the same color within floating-point rounding.
printf("round trip close: %s\n", $p3->to(ColorSpaces::srgb())->isCloseTo($blue, \Domm98CZ\Color\Math\Tolerance::absolute(1e-12)) ? 'yes' : 'no');
