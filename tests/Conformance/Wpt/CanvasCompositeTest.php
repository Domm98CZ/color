<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Conformance\Wpt;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Compositing\Compositor;
use Domm98CZ\Color\Compositing\PorterDuff;
use Domm98CZ\Color\Tests\Support\Fixture\FixtureLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Porter–Duff operators against the web-platform-tests canvas compositing tests
 * (frozen fixture, extracted by tools/wpt/extract-canvas-composite.php).
 */
final class CanvasCompositeTest extends TestCase
{
    private const string FIXTURE = 'wpt/canvas-composite.json';

    /**
     * @return iterable<string, array{PorterDuff, list<float>, list<float>, list<int>, int}>
     */
    public static function cases(): iterable
    {
        foreach (FixtureLoader::reference(self::FIXTURE)->cases as $case) {
            $file = $case['file'] ?? null;
            $operator = $case['operator'] ?? null;
            $tolerance = $case['tolerance'] ?? null;
            \assert(\is_string($file) && \is_string($operator) && \is_int($tolerance));

            yield $file => [
                PorterDuff::from($operator),
                self::numbers($case['destination'] ?? null),
                self::numbers($case['source'] ?? null),
                \array_map(static fn(float $v): int => (int) $v, self::numbers($case['expected'] ?? null)),
                $tolerance,
            ];
        }
    }

    /**
     * @param list<float> $destination
     * @param list<float> $source
     * @param list<int> $expected
     */
    #[DataProvider('cases')]
    public function testPixel(PorterDuff $operator, array $destination, array $source, array $expected, int $tolerance): void
    {
        $result = Compositor::porterDuff($operator)->composite(
            Color::fromRgba((int) $source[0], (int) $source[1], (int) $source[2], $source[3]),
            Color::fromRgba((int) $destination[0], (int) $destination[1], (int) $destination[2], $destination[3]),
        );

        $actual = [...\array_map(static fn(?float $v): float => ($v ?? 0.0) * 255, $result->coordinates()), $result->alpha() * 255];

        self::assertSame('srgb', $result->space()->id());
        foreach ($expected as $index => $value) {
            self::assertEqualsWithDelta($value, $actual[$index], $tolerance, (string) $index);
        }
    }

    /**
     * @return list<float>
     */
    private static function numbers(mixed $values): array
    {
        \assert(\is_array($values) && \array_is_list($values));

        return \array_map(FixtureLoader::number(...), $values);
    }
}
