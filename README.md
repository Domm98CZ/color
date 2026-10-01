# color

[![Build Status](https://img.shields.io/github/actions/workflow/status/Domm98CZ/color/ci.yml?branch=main)](https://github.com/Domm98CZ/color/actions/workflows/ci.yml) [![Latest Version](https://img.shields.io/packagist/v/domm98cz/color)](https://packagist.org/packages/domm98cz/color) [![PHP Version](https://img.shields.io/packagist/php-v/domm98cz/color)](https://packagist.org/packages/domm98cz/color) [![License](https://img.shields.io/github/license/Domm98CZ/color)](LICENSE) [![Downloads](https://img.shields.io/packagist/dt/domm98cz/color)](https://packagist.org/packages/domm98cz/color)

Small color library for PHP 8.3+ with no runtime dependencies: parse hex, rgb, hsl or any CSS Color 4 color, convert between 21 color spaces, derive tints, shades and harmonies, measure contrast and write the result back for the web. Every computation names its method (space, metric, color wheel), so two tools built on it agree with each other; not released yet, so the API may still change before 1.0 ([UPGRADE.md](UPGRADE.md) lists every change).

## Install

```bash
composer require domm98cz/color
```

## Scope

The library is deliberately small: a `Color` value, conversions, notation, differences, contrast,
gamut mapping, harmonies and scales for the web. It does not do ICC profiles or print/CMYK, color
appearance models or HDR, color vision deficiency simulation, WCAG success-criteria evaluation
(only the contrast ratio), palette optimization (changing a whole palette to meet constraints;
adjusting one color to a contrast target is included), or measurements and spectra. A feature
request outside this list needs an issue first, not a PR.

## Quick start

```php
use Domm98CZ\Color\Fluent\HexColor;
use Domm98CZ\Color\Fluent\RgbColor;

$brand = new HexColor('#3366cc');

$brand->lighten(0.1)->toHex();                         // "#5085ee" (OKLCh lightness +0.1)
$brand->complement()->toHex();                         // "#916300" (opposite hue on the OKLCh wheel)
$brand->mix(new RgbColor(255, 204, 0), 0.25)->toHex(); // "#6686b6" (25 % gold, mixed in OKLab)
$brand->shades(5);                                     // #264ea0 #193875 #0d234e #040f29 #010209
$brand->contrastWith(new HexColor('#fff'))->value;     // 5.366401794534009
```

The fluent layer (`HexColor`, `RgbColor`, `RgbaColor`, `HslColor`, `OklchColor`, `CssColor`) always outputs sRGB: a step that lands outside the gamut is mapped with the CSS Color 4 algorithm and recorded in `trace()`, never clipped silently. Every default it uses is listed in `FluentConfig` and can be swapped for a whole project (`FluentConfig::defaults()->withLightness(LightnessModel::HslL)` gives Sass-like results) or for one call. Runnable scripts for every feature below are in [`examples/`](examples/).

## Color spaces

The engine underneath the fluent layer is an immutable `Color` value in one of 21 built-in spaces: sRGB and linear sRGB, Display P3, A98 RGB, ProPhoto RGB, Rec. 2020, HSL, HWB, HSV, CIE XYZ D65/D50, CIE Lab/LCh D50 and D65, OKLab and OKLCh.

```php
use Domm98CZ\Color\Color;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Record\Oklch;

$blue = Color::fromHex('#3366cc');
$blue->in(Oklch::class)->h;                          // 262.2930486125381, null for achromatic colors
$blue->to(ColorSpaces::displayP3());                 // Display P3 coordinates 0.2499 0.3952 0.7736 (rounded)

Color::parse('color(display-p3 1 0 0)')->toHex();    // OutOfGamutException
```

Conversions never clip: a wide-gamut color converted to sRGB keeps its negative or above-one channels, and `toHex()` refuses it with an `OutOfGamutException` until you map it on purpose (see [Gamut mapping](#gamut-mapping)). A missing component (CSS `none`) is `null` in the color, behaves as zero in conversions and is carried over by interpolation, as CSS Color 4 specifies. A custom RGB space from primaries is a few lines (`examples/custom-space.php`).

## Notation

`Color::parse()` reads everything CSS Color 4 and 5 can write, including relative colors and `color-mix()` (results below as written by `toCss(new CssFormat(4))`):

```php
Color::parse('rebeccapurple');                          // rgb(102, 51, 153)
Color::parse('oklch(from #3366cc 0.8 c h)');            // oklch(0.8 0.1679 262.293)
Color::parse('color-mix(in oklch, #3366cc 30%, gold)'); // oklch(0.7805 0.1779 145.4193)

$blue->toJson();                                        // {"v":1,"space":"srgb","coords":[0.2,0.4,0.8],"alpha":1}
```

`toCss()` keeps out-of-gamut values; `toHex()` quantizes to 8 bits and is the only writer that refuses a color outside sRGB. JSON is lossless and versioned, so it is the format to store a color in.

## Harmonies and color wheels

There is no single "complementary color": the wheel decides, so every harmony takes one explicitly.

```php
use Domm98CZ\Color\Harmony\Harmony;
use Domm98CZ\Color\Harmony\HarmonyScheme;
use Domm98CZ\Color\Harmony\HueWheels;

Harmony::complementary($blue, HueWheels::hsl())->members[1]->color->toHex();  // "#cc9933"
Harmony::complementary($blue, HueWheels::ryb())->members[1]->color->toHex();  // "#cc8833"
Harmony::of($blue, HueWheels::oklch(), HarmonyScheme::triadic());             // #3366cc #ba363d #1b8316
```

On the OKLCh wheel the complement of `#3366cc` lies outside sRGB: the engine reports it through `$member->inGamut` and keeps the unclipped color, while the fluent `complement()` maps it to `#916300`. Analogous, split-complementary, tetradic, square and custom offset schemes follow the same shape.

## Tints, shades, scales

```php
use Domm98CZ\Color\Difference\DeltaE;
use Domm98CZ\Color\Gamut\GamutMappers;
use Domm98CZ\Color\Palette\Catalogs;
use Domm98CZ\Color\Palette\Scales;

Scales::tints(ColorSpaces::oklab(), 5)->generate($blue)->palette;
// #3366cc #5a86d9 #82a4e4 #abc3ee #d4e1f7

$scale = Scales::lightnessSteps(ColorSpaces::oklch(), [0.97, 0.85, 0.65, 0.45, 0.25])
    ->generate($blue, GamutMappers::cssColor4());
// #e9f6ff #acceff #558bf4 #1c4db0 #000670; $scale->mappedSteps() lists steps 0, 1 and 4

Catalogs::cssNamed()->nearest($blue, DeltaE::ciede2000())->entry->name;  // "royalblue" (ΔE00 3.34)
```

Lightness steps that fall outside sRGB are mapped with the mapper you pass and listed in `mappedSteps()`, so a design-system scale shows which of its colors are not what was asked for. The nearest named color is found by an explicit metric; a ΔE00 above roughly 2.3 is a visible difference, so check `->difference->value` before showing the name to a user.

## Contrast

```php
use Domm98CZ\Color\Contrast\Contrast;

Contrast::wcag2()->measure(Color::fromHex('#777'), Color::fromHex('#fff'))->value;  // 4.478089453577214
```

The ratio is never rounded: `#777` on white is 4.478, which fails the 4.5:1 threshold that a rounded "4.5" would pass. Only the ratio is computed; WCAG success criteria (text size, UI components) are the caller's decision. A translucent background throws `MissingBackdropException` until you say what is behind it (`LayerStack::of($overlay)->over($page)`). The fluent `isDark()` uses the same WCAG relative luminance with a 0.179 threshold, the point at which black and white text reach equal contrast.

To get a color that reaches a ratio, move it along its lightness instead of guessing:

```php
use Domm98CZ\Color\Contrast\ContrastAdjuster;
use Domm98CZ\Color\Operation\LightnessModel;

$adjuster = new ContrastAdjuster(Contrast::wcag2(), LightnessModel::OklchL);
$text = $adjuster->adjustForeground(Color::fromHex('#3366cc'), Color::fromHex('#fff'), 7.0);

$text->color->toHex();        // "#2253b8" (same hue, darker)
$text->contrast->value;       // 7.0059382501719
$text->reachable;             // true

(new HexColor('#3366cc'))->readableOn('#fff', 7.0)->toHex();  // "#2253b8" in a chain; readableBehind() adjusts a background
```

Hue and chroma are kept and only the lightness moves, in the model you name (OKLCh here); every candidate is mapped into sRGB and quantized to 8 bits, so the ratio reported is the ratio of the hex you write out. The passing candidate nearest to the input wins. A target no lightness can reach is not an error: the best candidate comes back with `reachable = false` and a `contrast-target-unreachable` warning (`#3366cc` on `#767676` cannot reach 7:1 in either direction). The target is a plain number; what 4.5, 7 or 3 mean for your text size remains your decision.

## Gamut mapping

```php
$p3Red = Color::parse('color(display-p3 1 0 0)');

GamutMappers::cssColor4()->map($p3Red, ColorSpaces::srgb())->color->toHex();  // "#ff0b0c"
```

Mapping is explicit in the engine and never happens as a side effect of a conversion. `cssColor4()` is the binary search in OKLCh from CSS Color 4; ray trace, chroma reduction and plain clipping are available under the same `GamutMapper` interface for when you need to match another tool.

## Color difference

```php
$a = Color::fromHex('#3366cc');
$b = Color::fromHex('#3a6bd0');

DeltaE::cie76()->difference($a, $b)->value;      // 2.196665323520467
DeltaE::ciede2000()->difference($a, $b)->value;  // 1.8384675223924878
DeltaE::ok()->difference($a, $b)->value;         // 0.016372279663656605
```

The metric is always named, because the three scales are not comparable: a ΔEOK of 0.016 and a ΔE00 of 1.84 describe the same pair. Each result carries the algorithm identity with its revision (`ciede2000[kL=1,kC=1,kH=1,white=D50]@r1`); any change of a computed value bumps that revision and is listed in [CHANGELOG.md](CHANGELOG.md).

## Extending

Every method the library uses is an interface you can implement: `ColorSpace`, `HueWheel`, `ColorDifferenceMetric`, `ContrastAlgorithm`, `GamutMapper`, `TransferFunction` and `ChromaticAdaptationTransform` (Bradford is the default adaptation between D65 and D50). A custom implementation plugs into the same services and results as the built-in ones, so a project-specific wheel or metric gets harmonies, scales and palette analysis for free.

From 1.0 the public API is everything in `src/` outside `*\Internal\*` and without `@internal`, including parameter names, enum values and numeric results; [`tests/Unit/Architecture/public-api.txt`](tests/Unit/Architecture/public-api.txt) makes every change visible in review.

## Contributing / running tests

```bash
composer install
composer check      # composer validate, PHP-CS-Fixer (PER-CS 2.0), PHPStan (max), all test suites
composer bench      # timings of common operations
```

See [CONTRIBUTING.md](CONTRIBUTING.md). The library supports PHP 8.3 through 8.5; CI runs the unit, conformance, property and examples suites on 8.3, 8.4 and 8.5, plus a `prefer-lowest` job on PHP 8.3, and PHPStan and PHP-CS-Fixer on 8.4.
