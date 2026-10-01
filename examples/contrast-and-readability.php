<?php

declare(strict_types=1);

// Pick black or white text for a background and check the WCAG 2 contrast ratio. isDark() compares
// the relative luminance with 0.179, the point where black and white text are equally readable;
// the ratio is never rounded (4.499 is not 4.5). The last line shows a failing pair and how far
// off it is.
// Run: php examples/contrast-and-readability.php

use Domm98CZ\Color\Fluent\HexColor;

require __DIR__ . '/../vendor/autoload.php';

$white = new HexColor('#ffffff');
$black = new HexColor('#000000');

foreach (['#3366cc', '#ffcc00', '#1a1a1a', '#e0e0e0', '#916300', '#777777'] as $value) {
    $background = new HexColor($value);
    $text = $background->isDark() ? $white : $black;
    $ratio = $text->contrastWith($background)->value;

    printf(
        "%s  luminance %.3f  %s text  %5.2f:1  %s\n",
        $value,
        $background->luminance(),
        $background->isDark() ? 'white' : 'black',
        $ratio,
        $ratio >= 4.5 ? 'ok for body text' : 'large text only',
    );
}

// A pair that fails: gray on white, and what a slightly darker gray gives.
echo "\n";
printf("#777777 on white: %.4f:1 (fails 4.5)\n", (new HexColor('#777777'))->contrastWith($white)->value);
printf("#767676 on white: %.4f:1 (passes)\n", (new HexColor('#767676'))->contrastWith($white)->value);
