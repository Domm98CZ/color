<?php

declare(strict_types=1);

// Read and write CSS, HEX and JSON. HEX needs the sRGB gamut and refuses anything outside it,
// JSON keeps everything (missing components included) and reads back exactly, and parse errors
// say where and what was expected; document-dependent colors such as currentColor are refused.
// Run: php examples/notation.php

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Notation\CssFormat;
use Domm98CZ\Color\Notation\Exception\ColorParseException;
use Domm98CZ\Color\Notation\Exception\OutOfGamutException;
use Domm98CZ\Color\Notation\Exception\UnresolvableCssColorException;

require __DIR__ . '/../vendor/autoload.php';

// CSS Color 4 syntax, including the legacy comma form, named colors and `none`.
foreach (['#36c', 'rebeccapurple', 'rgba(51, 102, 204, 50%)', 'hsl(220 60% 50%)', 'oklch(62% 0.18 255)', 'color(display-p3 1 0 0)', 'lch(50 30 none)'] as $css) {
    $color = Color::parse($css);
    printf("%-26s -> %-12s %s\n", $css, $color->space()->id(), $color->toCss());
}

// HEX needs the sRGB gamut. Out-of-gamut colors are refused, never clipped silently.
echo Color::parse('hsl(220 60% 50%)')->toHex(), "\n";
try {
    Color::parse('color(display-p3 1 0 0)')->toHex();
} catch (OutOfGamutException $exception) {
    echo "HEX refused: outside sRGB\n";
}

// Precision of the CSS output (default: 6 decimal places).
echo Color::fromOklch(0.62345678, 0.18, 255.123)->toCss(new CssFormat(3)), "\n";

// JSON keeps everything, including missing components, and reads back exactly.
$json = Color::parse('oklch(0.7 0.1 none / 0.5)')->toJson();
echo $json, "\n";
printf("same after JSON: %s\n", Color::fromJson($json)->equals(Color::parse('oklch(0.7 0.1 none / 0.5)')) ? 'yes' : 'no');

// Errors say where and what was expected; document-dependent colors are refused explicitly.
try {
    Color::parse('rgb(10%, 50, 0)');
} catch (ColorParseException $exception) {
    printf("offset %d: %s\n", $exception->getOffset(), $exception->getExpected());
}
try {
    Color::parse('currentcolor');
} catch (UnresolvableCssColorException $exception) {
    echo 'unresolvable: ', $exception->getKeyword(), "\n";
}
