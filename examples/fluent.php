<?php

declare(strict_types=1);

// The fluent layer: start from the notation you have and chain. Defaults (OKLCh lightness, OKLab
// mixing, CSS Color 4 mapping into sRGB) are visible in FluentConfig and in trace(); on PHP 8.3
// wrap `new HexColor(...)` in parentheses before chaining.
// Run: php examples/fluent.php

use Domm98CZ\Color\Fluent\Fluent;
use Domm98CZ\Color\Fluent\FluentColor;
use Domm98CZ\Color\Fluent\FluentConfig;
use Domm98CZ\Color\Fluent\HexColor;
use Domm98CZ\Color\Fluent\HslColor;
use Domm98CZ\Color\Fluent\RgbColor;
use Domm98CZ\Color\Harmony\HarmonyScheme;
use Domm98CZ\Color\Harmony\HueWheels;
use Domm98CZ\Color\Notation\Exception\OutOfGamutException;
use Domm98CZ\Color\Operation\LightnessModel;
use Domm98CZ\Color\Operation\Mixer;
use Domm98CZ\Color\Space\ColorSpaces;

require __DIR__ . '/../vendor/autoload.php';

// The notation constructors: the same chains, started from the notation you have. On PHP 8.3 the
// parentheses around `new` are required; PHP 8.4 also accepts `new HexColor('#3366cc')->lighten()`.
$hex = static fn(FluentColor $c): string => $c->toHex();
echo (new HexColor('#3366cc'))->lighten(0.08)->toHex(), "\n";
echo (new RgbColor(51, 102, 204))->complement()->toHex(), "\n";
echo (new HslColor(220, 60, 50))->tint(0.3)->toCss(), "\n";
echo implode(' ', array_map($hex, (new HexColor('#3366cc'))->shades(3))), "\n";
echo implode(' ', array_map($hex, (new HexColor('#3366cc'))->triadic())), "\n";
printf("dark: %s, luminance %.3f\n", (new HexColor('#3366cc'))->isDark() ? 'yes' : 'no', (new HexColor('#3366cc'))->luminance());

// Defaults (FluentConfig::defaults()): OKLCh lightness/chroma/hue, OKLab mixing, OKLCh wheel,
// output mapped into sRGB with the CSS Color 4 gamut mapper.
$fluent = Fluent::create();

echo $fluent->color('#3366cc')->lighten(0.08)->scaleChroma(1.2)->mix('#ffcc00', 0.25)->toHex(), "\n";

// One step with another model; units follow the model (HSL l is 0–100).
echo $fluent->color('#3366cc')->lighten(10, LightnessModel::HslL)->toCss(), "\n";

// A Sass-like configuration: HSL lightness, mixing in gamma-encoded sRGB, the HSL wheel.
$sassLike = new Fluent(FluentConfig::defaults()
    ->withLightness(LightnessModel::HslL)
    ->withMixer(new Mixer(ColorSpaces::srgb()))
    ->withWheel(HueWheels::hsl()));
echo $sassLike->color('#3366cc')->lighten(10)->mix('#ffcc00', 0.25)->complement()->toHex(), "\n";

// What happened: every step with its parameters, and the output mapping.
$chain = $fluent->color('#3366cc')->lighten(0.3)->scaleChroma(1.5);
foreach ($chain->trace()->steps as $step) {
    echo $step->operation, ' ', json_encode($step->parameters, \JSON_THROW_ON_ERROR), "\n";
}
$output = $chain->trace()->output;
assert($output !== null);
printf("unmapped %s, in gamut: %s, mapped %s by %s (ΔEOK %.3f)\n", $chain->color()->toCss(), $output->wasInGamut ? 'yes' : 'no', $chain->toHex(), $output->method->name, $output->difference->value);

// Strict mode: no automatic mapping; toHex() refuses colors outside sRGB.
$strict = new Fluent(FluentConfig::defaults()->withOutputMapping(null));
try {
    $strict->color('#3366cc')->lighten(0.3)->scaleChroma(1.5)->toHex();
} catch (OutOfGamutException) {
    echo "strict: out of sRGB, no hex\n";
}

// Results that are not colors are the modules' result objects.
printf("contrast on white: %.2f\n", $fluent->color('#3366cc')->contrastWith('#fff')->value);
echo implode(' ', array_map(static fn($member): string => $member->color->toCss(), $fluent->color('#3366cc')->harmony(HarmonyScheme::triadic())->members)), "\n";
