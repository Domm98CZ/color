<?php

declare(strict_types=1);

// Color differences and WCAG 2 contrast. The metric is always named because the three scales are
// not comparable, the contrast ratio is never rounded, and a translucent background needs to know
// what is behind it before a ratio exists.
// Run: php examples/difference-and-contrast.php

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Compositing\LayerStack;
use Domm98CZ\Color\Contrast\Contrast;
use Domm98CZ\Color\Contrast\Exception\MissingBackdropException;
use Domm98CZ\Color\Difference\DeltaE;

require __DIR__ . '/../vendor/autoload.php';

// The metric is always explicit; results are not rounded and carry the algorithm identity.
$a = Color::fromHex('#3366cc');
$b = Color::fromHex('#3a6bd0');
foreach ([DeltaE::cie76(), DeltaE::ciede2000(), DeltaE::ok()] as $metric) {
    $difference = $metric->difference($a, $b);
    printf("%-42s %.4f\n", $difference->metric->identifier(), $difference->value);
}

// WCAG 2 contrast: a number only, never rounded (4.478 is not 4.5).
$gray = Color::fromHex('#777777');
$white = Color::fromHex('#ffffff');
printf("contrast #777 on #fff: %.6f\n", Contrast::wcag2()->measure($gray, $white)->value);

// A translucent background needs to know what is behind it.
try {
    Contrast::wcag2()->measure($gray, Color::fromRgba(255, 255, 255, 0.5));
} catch (MissingBackdropException $exception) {
    echo 'missing: ', implode(', ', $exception->getMissing()), "\n";
}
$onPage = Contrast::wcag2()->measure($gray, LayerStack::of(Color::fromRgba(255, 255, 255, 0.5))->over(Color::fromHex('#000000')));
printf("over a stack: %.4f (background %s)\n", $onPage->value, $onPage->background->toHex());
