<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Examples;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs every script in examples/ in a separate PHP process and compares its output with
 * examples/expected/<name>.txt, so the documentation cannot drift from the real API.
 */
final class ExamplesTest extends TestCase
{
    private const string EXAMPLES = __DIR__ . '/../../examples';

    /**
     * @return iterable<string, array{string}>
     */
    public static function examples(): iterable
    {
        $files = \glob(self::EXAMPLES . '/*.php');
        self::assertIsArray($files);
        self::assertNotSame([], $files);

        foreach ($files as $file) {
            yield \basename($file) => [$file];
        }
    }

    #[DataProvider('examples')]
    public function testExampleOutputMatches(string $file): void
    {
        $expectedFile = self::EXAMPLES . '/expected/' . \basename($file, '.php') . '.txt';
        self::assertFileExists($expectedFile);

        $process = \proc_open([\PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', $file], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = \stream_get_contents($pipes[1]);
        $errors = \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        $exitCode = \proc_close($process);

        self::assertSame('', $errors, 'The example must not write to stderr.');
        self::assertSame(0, $exitCode);
        self::assertSame(\file_get_contents($expectedFile), $output);
    }
}
