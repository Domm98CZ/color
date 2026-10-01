<?php

declare(strict_types=1);

// Move a brand color along its OKLCh lightness until it reads at 4.5:1 or 7:1 on a background,
// keeping the hue. The ratio reported is the ratio of the hex written out, because every candidate
// is quantised to 8 bits before it is measured. A target no lightness can reach is a result with
// reachable = false and a warning, not an exception.
// Run: php examples/readable-colors.php

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Contrast\Contrast;
use Domm98CZ\Color\Contrast\ContrastAdjuster;
use Domm98CZ\Color\Fluent\HexColor;
use Domm98CZ\Color\Operation\LightnessModel;

require __DIR__ . '/../vendor/autoload.php';

$adjuster = new ContrastAdjuster(Contrast::wcag2(), LightnessModel::OklchL);
$white = Color::fromHex('#ffffff');

foreach ([4.5, 7.0] as $target) {
    foreach (['#3366cc', '#ffcc00', '#916300'] as $hex) {
        $result = $adjuster->adjustForeground(Color::fromHex($hex), $white, $target);

        printf(
            "%s on white at %.1f:1 -> %s  %7.4f:1  L %+.3f%s\n",
            $hex,
            $target,
            $result->color->toHex(),
            $result->contrast->value,
            $result->lightnessChange,
            $result->mapping->wasInGamut ? '' : '  (gamut mapped)',
        );
    }
}

// A tinted background for dark text: start from a light tint with the hue of the brand color.
$text = Color::fromHex('#1a1a1a');
$tint = Color::fromOklch(0.97, 0.03, 262.3);
$background = $adjuster->adjustBackground($tint, $text, 7.0);
printf("\n#1a1a1a on a blue tint: %s  %.2f:1\n", $background->color->toHex(), $background->contrast->value);

// Mid gray cannot give 7:1 to anything: the best candidate is returned and reachable is false.
$gray = $adjuster->adjustForeground(Color::fromHex('#3366cc'), Color::fromHex('#767676'), 7.0);
printf("\n#3366cc on #767676 at 7:1 -> %s  %.2f:1  reachable: %s\n", $gray->color->toHex(), $gray->contrast->value, $gray->reachable ? 'yes' : 'no');
printf("warning: %s\n", $gray->diagnostics->warnings[0]->code->value);

// The same in a fluent chain (the chain is the text).
printf("\nfluent: %s\n", (new HexColor('#3366cc'))->readableOn('#ffffff', 7.0)->toHex());
