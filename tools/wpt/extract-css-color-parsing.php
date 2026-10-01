<?php

declare(strict_types=1);

/*
 * Extracts the test data of web-platform-tests css/css-color/parsing into a reference fixture.
 *
 * Usage (see tools/wpt/README.md):
 *   php tools/wpt/extract-css-color-parsing.php <directory with the downloaded .html files> <commit> \
 *       > fixtures/reference/wpt/css-color-parsing.json
 *
 * No JavaScript is executed. The script reads the static test data only:
 *   - rows of `tests = [ [...], ... ];` arrays, run by a `for (const test of tests)` loop;
 *   - direct calls test_valid_value / test_computed_value / test_invalid_value /
 *     fuzzy_test_valid_color / fuzzy_test_computed_color with string literals;
 *   - calls inside `for (const x of [...])` / `for (const x of CONST)` loops whose template literals
 *     use only the loop variable.
 * Everything else (templates with other expressions) is counted in meta.skipped_by_extractor.
 */

const FILES = [
    'color-valid.html',
    'color-valid-rgb.html', 'color-invalid-rgb.html', 'color-computed-rgb.html',
    'color-valid-hsl.html', 'color-invalid-hsl.html', 'color-computed-hsl.html',
    'color-valid-hwb.html', 'color-invalid-hwb.html', 'color-computed-hwb.html',
    'color-valid-lab.html', 'color-invalid-lab.html', 'color-computed-lab.html',
    'color-valid-color-function.html', 'color-invalid-color-function.html', 'color-computed-color-function.html',
    'color-invalid-hex-color.html', 'color-computed-hex-color.html',
    'color-invalid-named-color.html', 'color-computed-named-color.html',
    'color-valid-color-mix-function.html', 'color-invalid-color-mix-function.html', 'color-computed-color-mix-function.html',
    'color-valid-relative-color.html', 'color-invalid-relative-color.html', 'color-computed-relative-color.html',
];

const FUNCTIONS = [
    'test_valid_value' => ['valid', false],
    'fuzzy_test_valid_color' => ['valid', true],
    'test_computed_value' => ['computed', false],
    'fuzzy_test_computed_color' => ['computed', true],
    'test_invalid_value' => ['invalid', false],
];

const STRING = '"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|`(?:[^`\\\\]|\\\\.)*`';

/**
 * @return array{string, bool}|null the literal's value and whether it is a template with ${…}
 */
function literal(string $token): ?array
{
    if ($token[0] === '"') {
        $value = json_decode($token);

        return is_string($value) ? [$value, false] : null;
    }

    if ($token[0] === "'") {
        // JavaScript single-quoted string: the same escapes as a JSON string, plus \'.
        $value = json_decode('"' . str_replace(["\\'", '"'], ["'", '\\"'], substr($token, 1, -1)) . '"');

        return is_string($value) ? [$value, false] : null;
    }

    $value = substr($token, 1, -1);

    return [$value, str_contains($value, '${')];
}

/**
 * @param array<string, list<string>> $constants
 *
 * @return list<string>|null
 */
function listLiteral(string $source, array $constants): ?array
{
    $values = [];
    $items = preg_split('/\s*,\s*/', trim($source));
    foreach ($items === false ? [] : $items as $item) {
        if ($item === '') {
            continue;
        }
        if (preg_match('/^\.\.\.([A-Z_]+)$/', $item, $spread) === 1 && isset($constants[$spread[1]])) {
            array_push($values, ...$constants[$spread[1]]);
            continue;
        }
        $parsed = literal($item);
        if ($parsed === null || $parsed[1]) {
            return null;
        }
        $values[] = $parsed[0];
    }

    return $values;
}

/**
 * Calls of the test functions in $code, with the loop variable substituted.
 *
 * @param array<string, string> $variables
 *
 * @return array{list<array<string, mixed>>, int}
 */
function calls(string $file, string $code, array $variables): array
{
    $cases = [];
    $skipped = 0;
    $pattern = '/\b(' . implode('|', array_keys(FUNCTIONS)) . ')\(\s*(?:"color"\s*,\s*)?(' . STRING . ')(?:\s*,\s*(' . STRING . '))?/s';

    preg_match_all($pattern, $code, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        [$kind, $fuzzy] = FUNCTIONS[$match[1]];
        $strings = [];

        foreach ([$match[2], $match[3] ?? ''] as $token) {
            if ($token === '') {
                $strings[] = null;
                continue;
            }
            [$value, $template] = literal($token) ?? ['', true];
            if ($template) {
                $value = strtr($value, array_combine(array_map(static fn(string $name): string => '${' . $name . '}', array_keys($variables)), array_values($variables)));
                if (str_contains($value, '${')) {
                    $skipped++;
                    continue 2;
                }
            }
            $strings[] = $value;
        }

        $cases[] = caseOf($file, $kind, $fuzzy, $strings[0] ?? '', $kind === 'invalid' ? null : ($strings[1] ?? $strings[0]));
    }

    return [$cases, $skipped];
}

/**
 * @return array<string, mixed>
 */
function caseOf(string $file, string $kind, bool $fuzzy, string $input, ?string $expected): array
{
    return ['file' => $file, 'kind' => $kind, 'fuzzy' => $fuzzy, 'input' => $input, 'expected' => $expected];
}

/** @var list<string> $arguments */
$arguments = array_map(static fn(mixed $v): string => is_scalar($v) ? (string) $v : '', (array) ($_SERVER['argv'] ?? []));
[$script, $directory, $commit] = $arguments + ['', '', ''];
if ($directory === '' || preg_match('/^[0-9a-f]{40}$/', $commit) !== 1) {
    fwrite(STDERR, "Usage: php {$script} <directory> <40-hex commit>\n");
    exit(2);
}

$cases = [];
$skipped = 0;

foreach (FILES as $file) {
    $html = file_get_contents($directory . '/' . $file);
    if ($html === false) {
        fwrite(STDERR, "Missing {$file}\n");
        exit(1);
    }

    $code = implode("\n", preg_match_all('#<script>(.*?)</script>#s', $html, $scripts) > 0 ? $scripts[1] : []);

    // Constants: const NAME = ["a", "b"];
    $constants = [];
    preg_match_all('/const ([A-Z_]+) = \[([^\]]*)\];/', $code, $definitions, PREG_SET_ORDER);
    foreach ($definitions as $definition) {
        $constants[$definition[1]] = listLiteral($definition[2], $constants) ?? [];
    }

    // tests = [ rows ]; run by: for (const test of tests) { FUNCTION(…test[0], test[1]…) }
    if (preg_match('/\btests = \[(.*?)\n\];/s', $code, $table) === 1
        && preg_match('/for \(const test of tests\) \{\s*(\w+)\(/', $code, $runner) === 1) {
        [$kind, $fuzzy] = FUNCTIONS[$runner[1]];
        preg_match_all('/^\s*\[(.*)\],?\s*(?:\/\/.*)?$/m', $table[1], $rows);
        foreach ($rows[1] as $row) {
            preg_match_all('/' . STRING . '/', $row, $strings);
            $values = array_map(static fn(string $token): string => literal($token)[0] ?? '', $strings[0]);
            if ($values === []) {
                continue;
            }
            $cases[] = caseOf($file, $kind, $fuzzy, $values[0], $kind === 'invalid' ? null : ($values[1] ?? $values[0]));
        }
        $code = str_replace($table[0], '', $code);
        $code = (string) preg_replace('/for \(const test of tests\) \{.*?\n\}/s', '', $code);
    }

    // for (const x of [ … ] | CONST | [...A, ...B]) { … }
    while (preg_match('/for \(const (\w+) of (\[[^\]]*\]|[A-Z_]+)\) \{/', $code, $loop, PREG_OFFSET_CAPTURE) === 1) {
        $start = $loop[0][1];
        $depth = 0;
        $end = $start;
        for ($i = $start; $i < strlen($code); $i++) {
            if ($code[$i] === '{') {
                $depth++;
            } elseif ($code[$i] === '}' && --$depth === 0) {
                $end = $i;
                break;
            }
        }
        $body = substr($code, $start, $end - $start + 1);
        $source = $loop[2][0];
        $values = $source[0] === '[' ? listLiteral(substr($source, 1, -1), $constants) : ($constants[$source] ?? null);

        foreach ($values ?? [] as $value) {
            [$found, $missed] = calls($file, $body, [$loop[1][0] => $value]);
            array_push($cases, ...$found);
            $skipped += $missed;
        }
        $code = substr($code, 0, $start) . substr($code, $end + 1);
    }

    [$found, $missed] = calls($file, $code, []);
    array_push($cases, ...$found);
    $skipped += $missed;
}

$fixture = [
    'meta' => [
        'id' => 'wpt-css-color-parsing',
        'source' => 'web-platform-tests, css/css-color/parsing (' . implode(', ', FILES) . ')',
        'edition' => 'commit ' . $commit,
        'url' => 'https://github.com/web-platform-tests/wpt/tree/' . $commit . '/css/css-color/parsing',
        'retrieved' => gmdate('Y-m-d'),
        'license' => '3-Clause BSD License, Copyright (c) web-platform-tests contributors (fixtures/reference/wpt/LICENSE.md)',
        'notes' => 'Expected strings are browser serializations. kind: valid = specified value, computed = computed value, invalid = must not parse. fuzzy = WPT compares numbers with epsilon 0.01.',
        'tolerance' => [
            'abs' => 0.01,
            'rationale' => 'WPT fuzzy color comparison epsilon (css/support/color-testcommon.js, default 0.01) applied to every number of the serialization. CSS Color 4 leaves the serialized precision to the implementation above a minimum, so exact string equality with one browser is not a conformance requirement.',
        ],
        'tolerance_overrides' => [
            'legacy-rgb-channels' => [
                'abs' => 0.5,
                'rationale' => 'Browsers store legacy sRGB colors (rgb(), hsl(), hwb(), named) at 8 bits and serialize rounded integers (hsl(60 100% 37.5%) -> rgb(191, 191, 0)); this library keeps binary64 (191.25). CSS Color 4 section 16.2.2 only requires at least 8-bit precision, so a difference of up to half an 8-bit step on the 0-255 channels of rgb()/rgba() is conformant.',
            ],
        ],
        'generator' => [
            'tool' => 'PHP ' . PHP_VERSION,
            'version' => $commit,
            'script' => 'tools/wpt/extract-css-color-parsing.php',
            'command' => 'php tools/wpt/extract-css-color-parsing.php <dir> ' . $commit,
        ],
        'skipped_by_extractor' => $skipped,
    ],
    'cases' => $cases,
];

// Meta pretty-printed, one case per line (readable diffs, a third of the size).
$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
$meta = substr(json_encode(['meta' => $fixture['meta']], $flags | JSON_PRETTY_PRINT), 0, -2);
$lines = array_map(static fn(array $case): string => '  ' . json_encode($case, $flags), $cases);
echo $meta, ",\n \"cases\": [\n", implode(",\n", $lines), "\n ]\n}\n";
