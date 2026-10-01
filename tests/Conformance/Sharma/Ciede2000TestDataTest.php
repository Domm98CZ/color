<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Conformance\Sharma;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Difference\DeltaE;
use Domm98CZ\Color\Tests\Support\Fixture\FixtureLoader;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The 34 CIEDE2000 test pairs of Sharma, Wu & Dalal (2005), in both orders (ΔE00 is symmetric).
 */
final class Ciede2000TestDataTest extends TestCase
{
    use FloatAssertions;

    private const string FIXTURE = 'sharma-2005/ciede2000-test-data.json';

    /**
     * @return iterable<string, array{list<float>, list<float>, float}>
     */
    public static function pairs(): iterable
    {
        foreach (FixtureLoader::reference(self::FIXTURE)->cases as $case) {
            $pair = $case['pair'] ?? null;
            \assert(\is_int($pair));

            yield 'pair ' . $pair => [
                \array_map(FixtureLoader::number(...), self::list($case['reference'] ?? null)),
                \array_map(FixtureLoader::number(...), self::list($case['sample'] ?? null)),
                FixtureLoader::number($case['expected'] ?? null),
            ];
        }
    }

    /**
     * @param list<float> $reference
     * @param list<float> $sample
     */
    #[DataProvider('pairs')]
    public function testPublishedDifference(array $reference, array $sample, float $expected): void
    {
        $metric = DeltaE::ciede2000();
        $a = Color::fromLab($reference[0], $reference[1], $reference[2]);
        $b = Color::fromLab($sample[0], $sample[1], $sample[2]);
        $tolerance = FixtureLoader::reference(self::FIXTURE)->tolerance;

        self::assertFloatClose($expected, $metric->difference($a, $b)->value, $tolerance);
        self::assertFloatClose($expected, $metric->difference($b, $a)->value, $tolerance, 'symmetric');
    }

    /**
     * @return list<mixed>
     */
    private static function list(mixed $value): array
    {
        \assert(\is_array($value) && \array_is_list($value));

        return $value;
    }
}
