<?php

declare(strict_types=1);

// Gamut mapping, mixing, color-mix(), gradients, adjustments and compositing. Mapping is explicit
// and reports what it did, the mixing space changes the result, and every adjustment names the
// model it works in.
// Run: php examples/gamut-and-operations.php

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Compositing\BlendMode;
use Domm98CZ\Color\Compositing\Compositor;
use Domm98CZ\Color\Gamut\GamutMappers;
use Domm98CZ\Color\Interpolation\HueInterpolation;
use Domm98CZ\Color\Notation\CssFormat;
use Domm98CZ\Color\Operation\Adjuster;
use Domm98CZ\Color\Operation\ChromaModel;
use Domm98CZ\Color\Operation\CssColorMix;
use Domm98CZ\Color\Operation\Gradient;
use Domm98CZ\Color\Operation\HueModel;
use Domm98CZ\Color\Operation\LightnessModel;
use Domm98CZ\Color\Operation\Mixer;
use Domm98CZ\Color\Operation\Quantizer;
use Domm98CZ\Color\Operation\TintShadeTone;
use Domm98CZ\Color\Space\ColorSpaces;

require __DIR__ . '/../vendor/autoload.php';

$format = new CssFormat(4);

// Gamut mapping is explicit; the mapper is chosen, and the result says what happened.
$p3 = Color::parse('color(display-p3 1 0.5 0)');
foreach ([GamutMappers::cssColor4(), GamutMappers::rayTrace(), GamutMappers::clip()] as $mapper) {
    $result = $mapper->map($p3, ColorSpaces::srgb());
    printf("%-38s %s  ΔEOK %.4f\n", $result->method->name, $result->color->toHex(), $result->difference->value);
}

// Mixing: the space decides the result.
$blue = Color::fromHex('#0000ff');
$white = Color::fromHex('#ffffff');
foreach ([ColorSpaces::srgb(), ColorSpaces::srgbLinear(), ColorSpaces::oklab(), ColorSpaces::oklch()] as $space) {
    printf("mix in %-12s %s\n", $space->id(), (new Mixer($space))->mix($blue, $white)->toCss($format));
}

// CSS color-mix() and relative colors, parsed or through the API: the same color.
echo Color::parse('color-mix(in oklch longer hue, #3366cc 30%, gold)')->toCss($format), "\n";
echo CssColorMix::mix(ColorSpaces::oklch(), HueInterpolation::Longer, [Color::fromHex('#3366cc'), 0.3], [Color::parse('gold'), null])->toCss($format), "\n";
echo Color::parse('oklch(from #3366cc 0.8 c h)')->toCss($format), "\n";

// Gradients: sample or write CSS.
$gradient = Gradient::of(Color::fromHex('#3366cc'), Color::fromHex('#ffcc00'))->in(ColorSpaces::oklch());
echo implode(' ', array_map(static fn(Color $c): string => GamutMappers::cssColor4()->map($c, ColorSpaces::srgb())->color->toHex(), $gradient->sample(5))), "\n";
echo $gradient->toCss(90.0, $format), "\n";

// Adjustments name their model; nothing is clipped.
$adjust = new Adjuster();
$base = Color::fromHex('#3366cc');
echo $adjust->shiftLightness($base, 0.1, LightnessModel::OklchL)->toCss($format), "\n";
echo $adjust->shiftLightness($base, 10.0, LightnessModel::HslL)->toCss($format), "\n";
echo $adjust->scaleChroma($base, 0.5, ChromaModel::Oklch)->toCss($format), "\n";
echo $adjust->rotateHue($base, 180.0, HueModel::Oklch)->toCss($format), "\n";
$tst = new TintShadeTone(ColorSpaces::oklab());
echo $tst->tint($base, 0.3)->toCss($format), ' ', $tst->shade($base, 0.3)->toCss($format), ' ', $tst->tone($base, 0.3)->toCss($format), "\n";
echo json_encode((new Quantizer(10))->quantize($base)->codes), "\n";

// Compositing: Porter–Duff operators and blend modes.
$top = Color::fromSrgb(1.0, 0.4, 0.2, 0.6);
echo Compositor::sourceOver()->composite($top, $white)->toCss($format), "\n";
echo Compositor::blend(BlendMode::Multiply)->composite($top, Color::fromHex('#88aaff'))->toCss($format), "\n";
echo Compositor::sourceOver(ColorSpaces::srgbLinear())->composite($top, $white)->toCss($format), "\n";
