<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Conformance\CssColor4;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Gamut\Contract\GamutMapper;
use Domm98CZ\Color\Gamut\GamutMappers;
use Domm98CZ\Color\Space\ColorSpaceRegistry;
use Domm98CZ\Color\Tests\Support\Fixture\FixtureLoader;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The gamut-mapping examples in the text of CSS Color 4 § 14.
 */
final class GamutMappingExamplesTest extends TestCase
{
    use FloatAssertions;

    private const string FIXTURE = 'css-color-4/gamut-mapping-examples.json';

    /**
     * @return iterable<string, array{GamutMapper, string, string, list<float>}>
     */
    public static function examples(): iterable
    {
        foreach (FixtureLoader::reference(self::FIXTURE)->cases as $case) {
            $input = $case['input'] ?? null;
            $destination = $case['destination'] ?? null;
            $expected = $case['expected'] ?? null;
            \assert(\is_string($input) && \is_string($destination) && \is_array($expected) && \array_is_list($expected));

            $mapper = match ($case['mapper'] ?? null) {
                'css-color-4-binary-search-local-minde' => GamutMappers::cssColor4(),
                'oklch-chroma-reduction' => GamutMappers::chromaReduction(),
                'clip' => GamutMappers::clip(),
                default => throw new \UnexpectedValueException('Unknown mapper in the fixture.'),
            };

            yield \sprintf('%s → %s (%s)', $input, $destination, $mapper->id()->name) => [$mapper, $input, $destination, \array_map(FixtureLoader::number(...), $expected)];
        }
    }

    /**
     * @param list<float> $expected
     */
    #[DataProvider('examples')]
    public function testExample(GamutMapper $mapper, string $input, string $destination, array $expected): void
    {
        $result = $mapper->map(Color::parse($input), ColorSpaceRegistry::default()->get($destination));
        $actual = \array_map(static fn(?float $v): float => $v ?? 0.0, $result->color->coordinates());

        self::assertSame($destination, $result->color->space()->id());
        self::assertTrue($result->color->isInGamut());
        foreach ($expected as $index => $value) {
            self::assertFloatClose($value, $actual[$index], FixtureLoader::reference(self::FIXTURE)->tolerance, (string) $index);
        }
    }
}
