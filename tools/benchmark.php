<?php

declare(strict_types=1);

/*
 * Times common operations (no dependencies).
 *
 * Run: php tools/benchmark.php [iterations]
 */

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Difference\DeltaE;
use Domm98CZ\Color\Space\ColorSpaceRegistry;
use Domm98CZ\Color\Space\ColorSpaces;

require __DIR__ . '/../vendor/autoload.php';

$iterations = max(100, (int) ($argv[1] ?? 2000));
$srgb = Color::fromHex('#4a90e2');
$sample = Color::fromHex('#5a8fd0');
$oklch = Color::fromOklch(0.7, 0.25, 145.0);

$cases = [];
foreach (['srgb-linear', 'xyz-d65', 'hsl', 'display-p3', 'oklab', 'oklch', 'lab'] as $id) {
    $space = ColorSpaceRegistry::default()->get($id);
    $cases["sRGB → $id"] = static fn() => $srgb->to($space);
}
$cases['OKLCh → sRGB'] = static fn() => $oklch->to(ColorSpaces::srgb());
$cases['Color::fromHex()'] = static fn() => Color::fromHex('#4a90e2');
$cases['Color::parse(oklch)'] = static fn() => Color::parse('oklch(70% 0.25 145 / 0.5)');
$cases['toCss()'] = static fn() => $oklch->toCss();
foreach (['cie76' => DeltaE::cie76(), 'ciede2000' => DeltaE::ciede2000(), 'ok' => DeltaE::ok()] as $name => $metric) {
    $cases["ΔE $name"] = static fn() => $metric->difference($srgb, $sample);
}

printf("PHP %s, %d iterations, median of 5 runs\n\n", PHP_VERSION, $iterations);
foreach ($cases as $name => $case) {
    $case();
    $runs = [];
    for ($run = 0; $run < 5; $run++) {
        $start = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $case();
        }
        $runs[] = (hrtime(true) - $start) / $iterations / 1000;
    }
    sort($runs);
    printf("%-24s %8.1f µs\n", $name, $runs[2]);
}
