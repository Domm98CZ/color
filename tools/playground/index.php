<?php

declare(strict_types=1);

/*
 * Visual playground: type a color, see what the library does with it (swatches, not hex strings).
 * Not part of the library or the test suite; a developer tool like tools/benchmark.php.
 *
 * Web:  php -S 0.0.0.0:8080 -t tools/playground      then open http://localhost:8080/?color=%233366cc
 * CLI:  php tools/playground/index.php '#3366cc' > out.html
 */

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Difference\DeltaE;
use Domm98CZ\Color\Fluent\FluentColor;
use Domm98CZ\Color\Harmony\HueWheels;
use Domm98CZ\Color\Notation\CssFormat;
use Domm98CZ\Color\Notation\Exception\ColorParseException;
use Domm98CZ\Color\Notation\Exception\UnresolvableCssColorException;
use Domm98CZ\Color\Palette\Catalogs;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Record\Oklch;

require __DIR__ . '/../../vendor/autoload.php';

$isCli = PHP_SAPI === 'cli';
$arguments = $_SERVER['argv'] ?? [];
$raw = $isCli ? (is_array($arguments) ? ($arguments[1] ?? null) : null) : ($_GET['color'] ?? null);
$input = is_string($raw) && trim($raw) !== '' ? trim($raw) : '#3366cc';

$h = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$format = new CssFormat(4);

$error = null;
$color = null;
try {
    $color = Color::parse($input);
} catch (ColorParseException|UnresolvableCssColorException $exception) {
    $error = $exception->getMessage();
}

// A swatch: the color as background, the label and the hex on top, in black or white text.
$swatch = static function (FluentColor $c, string $label = '', string $size = 'm') use ($h): string {
    $hex = $c->toHex();
    $text = $c->isDark() ? '#ffffff' : '#000000';
    $caption = $label === '' ? $hex : $label . '<br>' . $h($hex);

    return sprintf('<div class="sw sw-%s" style="background:%s;color:%s"><span>%s</span></div>', $size, $h($hex), $text, $caption);
};

$row = static fn(string $title, string $content): string => sprintf('<section><h2>%s</h2><div class="row">%s</div></section>', $h($title), $content);

$badge = static fn(float $ratio, float $threshold): string => $ratio >= $threshold
    ? sprintf('<span class="badge ok">&#10003; %.2f:1 &ge; %s</span>', $ratio, $threshold)
    : sprintf('<span class="badge fail">&#10007; %.2f:1 &lt; %s</span>', $ratio, $threshold);

$sections = '';

if ($color !== null) {
    $c = FluentColor::of($color);
    $hex = $c->toHex();
    $rgb = $c->toRgb();
    $hsl = $c->toHsl();
    $oklch = $c->toColor()->in(Oklch::class);

    // The color itself with its notations.
    $sections .= sprintf(
        '<section class="hero"><div class="sw sw-xl" style="background:%s"></div><div class="facts"><h1>%s</h1><p>%s</p><p>rgb(%.0f, %.0f, %.0f)</p><p>hsl(%s, %.1f%%, %.1f%%)</p><p>oklch(%.4f %.4f %s)</p><p>%s, luminance %.3f</p></div></section>',
        $h($hex),
        $h($input),
        $h($hex),
        $rgb->r,
        $rgb->g,
        $rgb->b,
        $hsl->h === null ? 'none' : sprintf('%.1f', $hsl->h),
        $hsl->s,
        $hsl->l,
        $oklch->l,
        $oklch->c,
        $oklch->h === null ? 'none' : sprintf('%.1f', $oklch->h),
        $c->isDark() ? 'dark' : 'light',
        $c->luminance(),
    );

    // Lighter and darker, in OKLCh (the default model).
    $steps = '';
    foreach ([-0.3, -0.2, -0.1, 0.0, 0.1, 0.2, 0.3] as $by) {
        $steps .= $swatch($by < 0 ? $c->darken(-$by) : $c->lighten($by), $by === 0.0 ? 'base' : sprintf('%+.1f L', $by));
    }
    $sections .= $row('Lighter and darker (OKLCh lightness)', $steps);

    // Scales.
    $scales = static fn(FluentColor ...$chains): string => implode('', array_map(static fn(FluentColor $x): string => $swatch($x), $chains));
    $sections .= $row('Tints (towards white)', $scales(...$c->tints(6)));
    $sections .= $row('Shades (towards black)', $scales(...$c->shades(6)));
    $sections .= $row('Tones (towards gray)', $scales(...$c->tones(6)));

    // Harmonies on three wheels.
    $wheels = ['OKLCh wheel (perceptual, default)' => HueWheels::oklch(), 'HSL wheel (web tools)' => HueWheels::hsl(), 'RYB wheel (painter\'s)' => HueWheels::ryb()];
    foreach ($wheels as $wheelName => $wheel) {
        $harmony = static fn(string $label, FluentColor ...$chains): string => sprintf('<div class="group"><h3>%s</h3>%s</div>', $h($label), implode('', array_map(static fn(FluentColor $x): string => $swatch($x), $chains)));
        $content = $harmony('complementary', $c, $c->complement($wheel))
            . $harmony('triadic', ...$c->triadic($wheel))
            . $harmony('split complementary', ...$c->splitComplementary(30.0, $wheel))
            . $harmony('analogous', ...$c->analogous(3, 30.0, $wheel));
        $sections .= $row($wheelName, $content);
    }

    // Contrast: the color as a background for white and black text, and as text on white and black.
    $contrast = '';
    foreach (['#ffffff' => 'white', '#000000' => 'black'] as $other => $otherName) {
        $ratio = $c->contrastWith($other)->value;
        $contrast .= sprintf(
            '<div class="contrast"><div class="preview" style="background:%s;color:%s">%s text on this color<br><small>The quick brown fox jumps over the lazy dog.</small></div><div class="preview" style="background:%s;color:%s">this color as text on %s<br><small>The quick brown fox jumps over the lazy dog.</small></div><p>%s body text (4.5:1) &nbsp; %s large text (3:1)</p></div>',
            $h($hex),
            $h($other),
            $otherName,
            $h($other),
            $h($hex),
            $otherName,
            $badge($ratio, 4.5),
            $badge($ratio, 3.0),
        );
    }
    $sections .= $row('WCAG 2 contrast', $contrast);

    // Nearest CSS named colors.
    $names = '';
    foreach (Catalogs::cssNamed()->rank($c->toColor(), DeltaE::ciede2000(), 4) as $match) {
        $names .= $swatch(FluentColor::of($match->entry->color), sprintf('%s (&Delta;E00 %.1f)', $h($match->entry->name), $match->difference->value));
    }
    $sections .= $row('Nearest CSS named colors (CIEDE2000)', $swatch($c, 'your color') . $names);

    // Gamut mapping: a vivid version of the color, and a Display P3 red, both outside sRGB.
    $vivid = $c->lighten(0.15)->scaleChroma(1.8);
    $p3 = FluentColor::of(Color::parse('color(display-p3 1 0 0)'));
    $mapping = static function (FluentColor $x, string $label) use ($swatch, $format, $h): string {
        $output = $x->trace()->output;
        $inGamut = $x->color()->isInGamut(ColorSpaces::srgb());

        return sprintf(
            '<div class="group"><h3>%s</h3>%s<p class="mono">unmapped: %s<br>%s</p></div>',
            $h($label),
            $swatch($x, $inGamut ? 'in sRGB, unchanged' : 'mapped into sRGB'),
            $h($x->color()->toCss($format)),
            $inGamut || $output === null ? 'inside the sRGB gamut' : sprintf('outside sRGB &rarr; %s by %s, &Delta;EOK %.3f', $h($x->toHex()), $h($output->method->name), $output->difference->value),
        );
    };
    $sections .= $row('Gamut mapping (CSS Color 4)', $mapping($vivid, 'your color, lighter and 1.8x more chroma') . $mapping($p3, 'color(display-p3 1 0 0)'));
}

$form = $isCli ? '' : sprintf(
    '<form method="get"><label>Color <input name="color" value="%s" placeholder="#3366cc, rgb(51 102 204), oklch(53%% 0.17 262), rebeccapurple"></label> <button>Show</button></form>',
    $h($input),
);

$errorBox = $error === null ? '' : sprintf('<p class="error">Could not read "%s": %s</p>', $h($input), $h($error));

$css = <<<'CSS'
    body { font: 15px/1.4 system-ui, sans-serif; margin: 0; padding: 24px; background: #fafafa; color: #222; }
    h1 { margin: 0 0 8px; font-size: 28px; } h2 { font-size: 17px; margin: 28px 0 8px; } h3 { font-size: 13px; margin: 8px 0 4px; font-weight: 600; color: #555; }
    form { margin-bottom: 20px; } input { font: inherit; padding: 6px 10px; width: 24em; } button { font: inherit; padding: 6px 14px; }
    .hero { display: flex; gap: 24px; align-items: flex-start; } .facts p { margin: 2px 0; font-family: ui-monospace, monospace; }
    .row { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-start; }
    .group { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; margin-right: 24px; } .group h3 { flex-basis: 100%; }
    .sw { display: flex; align-items: flex-end; border-radius: 6px; font-family: ui-monospace, monospace; font-size: 12px; box-shadow: inset 0 0 0 1px rgba(0,0,0,.08); }
    .sw span { padding: 6px 8px; } .sw-m { width: 112px; height: 84px; } .sw-xl { width: 220px; height: 220px; }
    .contrast { min-width: 320px; } .preview { padding: 14px 16px; border-radius: 6px; margin-bottom: 6px; font-weight: 600; } .preview small { font-weight: 400; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-family: ui-monospace, monospace; font-size: 12px; }
    .ok { background: #d9f2df; color: #0b6b2a; } .fail { background: #fbdcdc; color: #9b1c1c; }
    .mono { font-family: ui-monospace, monospace; font-size: 12px; margin: 4px 0; } .error { color: #9b1c1c; }
    CSS;

echo "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>colors playground: ", $h($input), "</title>\n<style>\n", $css, "\n</style>\n</head>\n<body>\n", $form, $errorBox, $sections, "\n</body>\n</html>\n";
