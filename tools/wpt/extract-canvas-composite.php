<?php

declare(strict_types=1);

/*
 * Extracts the Porter–Duff cases of web-platform-tests html/canvas/element/compositing
 * (2d.composite.{solid,transparent}.<operator>.html) into a reference fixture.
 *
 * Usage (see tools/wpt/README.md):
 *   php tools/wpt/extract-canvas-composite.php <directory with the .html files> <commit> \
 *       > fixtures/reference/wpt/canvas-composite.json
 *
 * Each test fills the canvas with a destination color, sets globalCompositeOperation and fills
 * with a source color; the expected pixel is checked with _assertPixelApprox(…, 5).
 */

const OPERATORS = ['source-over', 'source-in', 'source-out', 'source-atop', 'destination-over', 'destination-in', 'destination-out', 'destination-atop', 'xor', 'copy', 'lighter'];
const RGBA = '/rgba\((\d+), (\d+), (\d+), ([0-9.]+)\)/';

/** @var list<string> $arguments */
$arguments = array_map(static fn(mixed $v): string => is_scalar($v) ? (string) $v : '', (array) ($_SERVER['argv'] ?? []));
[$script, $directory, $commit] = $arguments + ['', '', ''];
if ($directory === '' || preg_match('/^[0-9a-f]{40}$/', $commit) !== 1) {
    fwrite(STDERR, "Usage: php {$script} <directory> <40-hex commit>\n");
    exit(2);
}

$cases = [];
foreach (['solid', 'transparent'] as $variant) {
    foreach (OPERATORS as $operator) {
        $file = "2d.composite.{$variant}.{$operator}.html";
        $html = file_get_contents($directory . '/' . $file);
        if ($html === false
            || preg_match_all('/fillStyle = \'([^\']+)\'/', $html, $fills) !== 2
            || preg_match('/globalCompositeOperation = \'([a-z-]+)\'/', $html, $op) !== 1
            || preg_match('/_assertPixelApprox\(canvas, 50,25, (\d+),(\d+),(\d+),(\d+), (\d+)\)/', $html, $pixel) !== 1
        ) {
            fwrite(STDERR, "Cannot read {$file}\n");
            exit(1);
        }

        $rgba = static function (string $css): array {
            if (preg_match(RGBA, $css, $m) !== 1) {
                throw new RuntimeException("Unexpected color {$css}");
            }

            return [(int) $m[1], (int) $m[2], (int) $m[3], (float) $m[4]];
        };

        $cases[] = [
            'file' => $file,
            'operator' => $op[1],
            'destination' => $rgba($fills[1][0]),
            'source' => $rgba($fills[1][1]),
            'expected' => [(int) $pixel[1], (int) $pixel[2], (int) $pixel[3], (int) $pixel[4]],
            'tolerance' => (int) $pixel[5],
        ];
    }
}

echo json_encode([
    'meta' => [
        'id' => 'wpt-canvas-composite',
        'source' => 'web-platform-tests, html/canvas/element/compositing (2d.composite.solid.* and 2d.composite.transparent.*)',
        'edition' => "commit {$commit}",
        'url' => "https://github.com/web-platform-tests/wpt/tree/{$commit}/html/canvas/element/compositing",
        'retrieved' => date('Y-m-d'),
        'license' => '3-Clause BSD License, Copyright (c) web-platform-tests contributors (fixtures/reference/wpt/LICENSE.md)',
        'notes' => 'Colors are rgba() with 0-255 channels and a 0-1 alpha; destination is painted first, then source with the operator. expected is the 8-bit RGBA pixel (non-premultiplied, as getImageData returns it); WPT accepts +/- tolerance per channel.',
        'tolerance' => [
            'abs' => 5,
            'rationale' => 'The tolerance of the WPT assertion (_assertPixelApprox, 5 of 255 per channel), which covers 8-bit storage and premultiplication rounding in browsers.',
        ],
        'generator' => [
            'tool' => 'PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION,
            'version' => $commit,
            'script' => 'tools/wpt/extract-canvas-composite.php',
            'command' => "php tools/wpt/extract-canvas-composite.php <dir> {$commit}",
        ],
    ],
    'cases' => $cases,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
