<?php

declare(strict_types=1);

// Harmonies of one brand color. There is no single "complementary color": the HSL wheel of web
// tools, the perceptual OKLCh wheel and the painter's RYB wheel each put the opposite somewhere
// else, so the wheel is always named. Every member is a chain again, ready for more steps.
// Run: php examples/harmony-schemes.php

use Domm98CZ\Color\Fluent\FluentColor;
use Domm98CZ\Color\Fluent\HexColor;
use Domm98CZ\Color\Harmony\HueWheels;

require __DIR__ . '/../vendor/autoload.php';

$brand = new HexColor('#3366cc');
$hex = static fn(FluentColor $c): string => $c->toHex();

foreach (['oklch (default)' => HueWheels::oklch(), 'hsl' => HueWheels::hsl(), 'ryb' => HueWheels::ryb()] as $name => $wheel) {
    printf("complement on %-16s %s\n", $name, $brand->complement($wheel)->toHex());
}

echo "\n";
printf("%-22s %s\n", 'triadic', implode(' ', array_map($hex, $brand->triadic())));
printf("%-22s %s\n", 'analogous 3 x 30 deg', implode(' ', array_map($hex, $brand->analogous())));
printf("%-22s %s\n", 'analogous 5 x 15 deg', implode(' ', array_map($hex, $brand->analogous(5, 15.0))));
printf("%-22s %s\n", 'split complementary', implode(' ', array_map($hex, $brand->splitComplementary())));
printf("%-22s %s\n", 'triadic on RYB', implode(' ', array_map($hex, $brand->triadic(HueWheels::ryb()))));

// Members chain on: a lighter tint of every triadic color, for backgrounds.
echo "\n", 'triadic tints         ', implode(' ', array_map(static fn(FluentColor $c): string => $c->tint(0.7)->toHex(), $brand->triadic())), "\n";
