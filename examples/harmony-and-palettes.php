<?php

declare(strict_types=1);

// Wheels, harmonies, scales, palette analysis and nearest named colors on the engine services.
// Harmony members and scale steps outside sRGB are reported, never clipped; a design-system scale
// lists which steps had to be mapped.
// Run: php examples/harmony-and-palettes.php

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Contrast\Contrast;
use Domm98CZ\Color\Difference\DeltaE;
use Domm98CZ\Color\Gamut\GamutMappers;
use Domm98CZ\Color\Harmony\Harmony;
use Domm98CZ\Color\Harmony\HarmonyScheme;
use Domm98CZ\Color\Harmony\HueWheels;
use Domm98CZ\Color\Palette\Catalogs;
use Domm98CZ\Color\Palette\Palette;
use Domm98CZ\Color\Palette\PaletteAnalyzer;
use Domm98CZ\Color\Palette\Scales;
use Domm98CZ\Color\Space\ColorSpaces;

require __DIR__ . '/../vendor/autoload.php';

$brand = Color::fromHex('#3366cc');
$hex = static fn(Color $color): string => $color->isInGamut() ? $color->toHex() : 'out of sRGB';

// There is no single "complementary color": the wheel decides.
foreach (['hsl' => HueWheels::hsl(), 'oklch' => HueWheels::oklch(), 'ryb' => HueWheels::ryb()] as $name => $wheel) {
    $set = Harmony::complementary($brand, $wheel);
    printf("complement on %-6s %s\n", $name, $hex($set->members[1]->color));
}

// Schemes are offsets on a wheel; members outside sRGB are reported, not clipped.
$triad = Harmony::of($brand, HueWheels::oklch(), HarmonyScheme::triadic());
foreach ($triad->members as $member) {
    printf("triadic %+6.1f°  %s\n", $member->offset, $member->inGamut ? $member->color->toHex() : 'out of sRGB: ' . $member->color->toCss());
}
echo implode(' ', array_map($hex, Harmony::analogous($brand, HueWheels::hsl(), 5, 20.0)->colors())), "\n";

// Classification on wheels with primaries.
foreach (['#ff8000', '#3366cc', '#808080'] as $css) {
    $ryb = HueWheels::ryb()->classify(Color::fromHex($css));
    printf("%s on the RYB wheel: %s %s\n", $css, $ryb->order->value, $ryb->position->name ?? '');
}

// A design-system scale: OKLCh lightness steps, mapped into sRGB, named 50…900.
$scale = Scales::lightnessSteps(ColorSpaces::oklch(), [0.97, 0.93, 0.85, 0.75, 0.65, 0.55, 0.45, 0.35, 0.25])
    ->generate($brand, GamutMappers::cssColor4());
$palette = $scale->palette->withNames(['50', '100', '200', '300', '400', '500', '600', '700', '800']);
foreach ($palette->entries() as $entry) {
    echo $entry->name, '=', $entry->color->toHex(), ' ';
}
echo "\nmapped steps: ", implode(', ', $scale->mappedSteps()), "\n";

// Tints.
echo implode(' ', array_map($hex, Scales::tints(ColorSpaces::oklab(), 5)->generate($brand)->palette->colors())), "\n";

// Analysis: the closest pair and a contrast matrix.
$analysis = (new PaletteAnalyzer(DeltaE::ciede2000()))->analyze(Palette::named([
    'brand' => $brand, 'brand-alt' => Color::fromHex('#3a6bd0'), 'paper' => Color::fromHex('#ffffff'), 'ink' => Color::fromHex('#1a1a1a'),
]));
printf("closest pair: %d and %d, ΔE00 %.2f\n", $analysis->minDifference->first ?? -1, $analysis->minDifference->second ?? -1, $analysis->minDifference->difference->value ?? 0.0);
printf("ink on paper: %.2f:1\n", $analysis->contrastMatrix(Contrast::wcag2())[3][2]);

// The nearest CSS named color, by an explicit metric.
$match = Catalogs::cssNamed()->nearest($brand, DeltaE::ciede2000());
printf("nearest named color: %s (ΔE00 %.2f)\n", $match->entry->name, $match->difference->value);
