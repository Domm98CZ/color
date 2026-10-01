<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Architecture;

use Domm98CZ\Color\Tests\Support\Api\PublicApi;
use PHPUnit\Framework\TestCase;

/**
 * Every change of the public API (names, parameter names, types, defaults, enum values, constants)
 * shows up as a diff of `public-api.txt`. After an intended change, regenerate the snapshot with
 * `php tools/api/snapshot.php`, review the diff and record the change in CHANGELOG.md (and, after
 * 1.0, in UPGRADE.md).
 */
final class PublicApiSnapshotTest extends TestCase
{
    private const string SNAPSHOT = __DIR__ . '/public-api.txt';

    public function testThePublicApiMatchesTheSnapshot(): void
    {
        $snapshot = \file_get_contents(self::SNAPSHOT);
        self::assertIsString($snapshot);

        self::assertSame(
            $snapshot,
            \implode("\n", PublicApi::describe(__DIR__ . '/../../../src')) . "\n",
            'The public API changed. If intended, run `php tools/api/snapshot.php`, review the diff and record the change in CHANGELOG.md.',
        );
    }

    public function testTheSnapshotDescribesSignaturesWithParameterNamesAndDefaults(): void
    {
        $lines = PublicApi::describe(__DIR__ . '/../../../src');

        self::assertContains('Color: final readonly class', $lines);
        self::assertContains('  method static fromOklch(float $lightness, float $chroma, float $hue, float $alpha = 1.0): Color', $lines);
        self::assertContains('  method toHex(bool $alwaysIncludeAlpha = false): string', $lines);
        self::assertContains('  const DEFAULT_GAMUT_TOLERANCE = 1.0E-6', $lines);
        self::assertContains('Contrast\Algorithm\Wcag2LuminanceThreshold: enum: string', $lines);
        self::assertContains("  case Wcag20 = '0.03928'", $lines);
        self::assertContains('Exception\ColorException: interface', $lines);
        self::assertContains('Exception\InsufficientDataException: abstract class', $lines);
        self::assertContains('  extends Exception\DomainException', $lines);
        self::assertContains('  implements Exception\ColorException', $lines);
        self::assertContains('Palette\Palette: final readonly class', $lines);
        self::assertContains('  implements Countable', $lines);
        self::assertContains('  property readonly Color $color', $lines);
        self::assertContains('  method composite(Compositing\Contract\CompositeOperator $operator = new Compositing\Algorithm\SourceOver()): Compositing\Result\CompositeResult', $lines);
        self::assertContains('  method static of(Color ...$colors): Palette\Palette', $lines);
        self::assertContains('  const CORNERS = [\'000\' => [1.0, 1.0, 1.0], 100 => [1.0, 0.0, 0.0], \'010\' => [1.0, 1.0, 0.0], \'001\' => [0.163, 0.373, 0.6], 110 => [1.0, 0.5, 0.0], 101 => [0.5, 0.0, 0.5], \'011\' => [0.0, 0.66, 0.2], 111 => [0.2, 0.094, 0.0]]', $lines, 'numeric-string keys are integers in PHP');

        $text = \implode("\n", $lines);
        self::assertStringNotContainsString('Internal\\', $text, 'internal classes are not part of the API');
        self::assertStringNotContainsString('implements Stringable', $text, 'implied interfaces are left out');
        self::assertStringNotContainsString('implements UnitEnum', $text);
        self::assertStringNotContainsString('method cases()', $text, 'generated enum methods are left out');
        self::assertStringNotContainsString('method static check(', $text, '@internal methods are left out');
        self::assertStringNotContainsString('Domm98CZ\\', $text, 'names are relative to the root namespace');
    }
}
