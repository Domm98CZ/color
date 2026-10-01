<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Conformance\CssColor5;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Space\ColorSpaceRegistry;
use Domm98CZ\Color\Tests\Support\Fixture\FixtureLoader;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The color-mix() and relative color examples in the text of CSS Color 5 (§ 3, § 4).
 */
final class ColorMixExamplesTest extends TestCase
{
    use FloatAssertions;

    private const string FIXTURE = 'css-color-5/color-mix-examples.json';

    /**
     * @return iterable<string, array{string, string, ?string, list<mixed>, float, Tolerance}>
     */
    public static function examples(): iterable
    {
        $fixture = FixtureLoader::reference(self::FIXTURE);

        foreach ($fixture->cases as $index => $case) {
            $input = $case['input'] ?? null;
            $space = $case['space'] ?? null;
            $compareIn = $case['compare_in'] ?? null;
            $expected = $case['expected'] ?? null;
            \assert(\is_string($input) && \is_string($space) && ($compareIn === null || \is_string($compareIn)) && \is_array($expected) && \array_is_list($expected));

            yield \sprintf('#%d %s%s', $index, $input, $compareIn === null ? '' : ' in ' . $compareIn) => [
                $input,
                $space,
                $compareIn,
                $expected,
                FixtureLoader::number($case['alpha'] ?? null),
                isset($case['abs']) ? Tolerance::absolute(FixtureLoader::number($case['abs'])) : $fixture->tolerance,
            ];
        }
    }

    /**
     * @param list<mixed> $expected
     */
    #[DataProvider('examples')]
    public function testExample(string $input, string $space, ?string $compareIn, array $expected, float $alpha, Tolerance $tolerance): void
    {
        $color = Color::parse($input);
        self::assertSame($space, $color->space()->id());

        if ($compareIn !== null) {
            $color = $color->to(ColorSpaceRegistry::default()->get($compareIn));
        }

        foreach ($expected as $index => $value) {
            $actual = $color->coordinates()[$index];

            if ($value === null) {
                self::assertNull($actual, (string) $index);
                continue;
            }

            self::assertNotNull($actual, (string) $index);
            self::assertFloatClose(FixtureLoader::number($value), $actual, $tolerance, (string) $index);
        }

        self::assertFloatClose($alpha, $color->alpha(), $tolerance, 'alpha');
    }
}
