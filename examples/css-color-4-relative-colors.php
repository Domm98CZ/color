<?php

declare(strict_types=1);

// Everything CSS Color 4 and 5 can write, the library can read: named colors, color(), oklch(),
// relative colors ("the same hue, lightness 0.8") and color-mix(). A wide-gamut color is kept as
// it is; toHex() refuses it until you map it into sRGB on purpose.
// Run: php examples/css-color-4-relative-colors.php

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Fluent\CssColor;
use Domm98CZ\Color\Gamut\GamutMappers;
use Domm98CZ\Color\Notation\CssFormat;
use Domm98CZ\Color\Notation\Exception\OutOfGamutException;
use Domm98CZ\Color\Space\ColorSpaces;

require __DIR__ . '/../vendor/autoload.php';

$format = new CssFormat(4);

foreach ([
    'rebeccapurple',
    'rgb(51 102 204 / 50%)',
    'oklch(62% 0.18 255)',
    'oklch(from #3366cc 0.8 c h)',
    'hsl(from #3366cc h s 80%)',
    'color-mix(in oklch, #3366cc 30%, gold)',
    'color-mix(in srgb, #3366cc, white)',
] as $css) {
    printf("%-42s -> %s\n", $css, Color::parse($css)->toCss($format));
}

// The fluent layer takes the same strings and maps the output into sRGB for you.
echo "\n";
printf("%-42s -> %s\n", 'CssColor oklch(from #3366cc 0.8 c h)', (new CssColor('oklch(from #3366cc 0.8 c h)'))->toHex());

// A Display P3 red is outside sRGB: the engine keeps it, hex needs an explicit mapping.
echo "\n";
$p3 = Color::parse('color(display-p3 1 0 0)');
printf("%-42s -> %s\n", 'color(display-p3 1 0 0) in sRGB terms', $p3->to(ColorSpaces::srgb())->toCss($format));
try {
    $p3->toHex();
} catch (OutOfGamutException) {
    echo str_pad('toHex()', 42), " -> refused: outside the sRGB gamut\n";
}
printf("%-42s -> %s\n", 'mapped with the CSS Color 4 algorithm', GamutMappers::cssColor4()->map($p3, ColorSpaces::srgb())->color->toHex());
printf("%-42s -> %s\n", 'CssColor(...)->toHex() maps for you', (new CssColor('color(display-p3 1 0 0)'))->toHex());
