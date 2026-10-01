<?php

declare(strict_types=1);

/*
 * Regenerates the public API snapshot compared by tests/Unit/Architecture/PublicApiSnapshotTest.php.
 *
 * Run: php tools/api/snapshot.php
 *
 * Review the diff: every changed line is a change of the public API.
 */

use Domm98CZ\Color\Tests\Support\Api\PublicApi;

require __DIR__ . '/../../vendor/autoload.php';

$target = __DIR__ . '/../../tests/Unit/Architecture/public-api.txt';
$lines = PublicApi::describe(__DIR__ . '/../../src');
file_put_contents($target, implode("\n", $lines) . "\n");
echo count($lines), " lines written to tests/Unit/Architecture/public-api.txt\n";
