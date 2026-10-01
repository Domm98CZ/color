<?php

declare(strict_types=1);

// A grey has no hue: after conversion to a polar space the hue is null, never a fake 0 degrees.
// A missing component (CSS `none`) stays null in the color and counts as zero in conversions;
// missing alpha reads as 0 but is remembered as missing.
// Run: php examples/missing-and-powerless.php

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Record\Oklch;

require __DIR__ . '/../vendor/autoload.php';

// A grey has no hue. After conversion to a polar space the hue is null, never a fake 0°.
$grey = Color::fromRgb(128, 128, 128);
$view = $grey->in(Oklch::class);
printf("grey hue: %s, chroma: %s\n", var_export($view->h, true), var_export($view->c, true));

// A missing component is null in the color and behaves as zero in conversions (CSS Color 4 §4.4).
$color = Color::of(ColorSpaces::oklch(), [0.7, 0.1, null]);
printf("missing components: %s\n", $color->hasMissingComponents() ? 'yes' : 'no');
vprintf("as srgb: %.6f %.6f %.6f\n", $color->to(ColorSpaces::srgb())->coordinates());

// Missing alpha reads as 0 but is remembered as missing.
$transparent = Color::fromRgb(255, 0, 0)->withAlpha(null);
printf("alpha: %s, missing: %s\n", var_export($transparent->alpha(), true), $transparent->isAlphaMissing() ? 'yes' : 'no');
