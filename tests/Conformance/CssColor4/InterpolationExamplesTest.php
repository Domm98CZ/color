<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Conformance\CssColor4;

use Domm98CZ\Color\Interpolation\HueInterpolation;
use Domm98CZ\Color\Interpolation\InterpolationMethod;
use Domm98CZ\Color\Interpolation\MissingComponents;
use Domm98CZ\Color\Interpolation\Segment;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Notation\CssParser;
use Domm98CZ\Color\Space\ColorSpaceRegistry;
use Domm98CZ\Color\Space\ColorValue;
use Domm98CZ\Color\Tests\Support\Fixture\FixtureLoader;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The interpolation examples in the text of CSS Color 4 § 13 (missing components, premultiplied
 * alpha, hue methods).
 */
final class InterpolationExamplesTest extends TestCase
{
    use FloatAssertions;

    private const string FIXTURE = 'css-color-4/interpolation-examples.json';

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function examples(): iterable
    {
        foreach (FixtureLoader::reference(self::FIXTURE)->cases as $index => $case) {
            $from = $case['from'] ?? null;
            \assert(\is_string($from));

            yield \sprintf('#%d § %s %s', $index, \is_string($case['section'] ?? null) ? $case['section'] : '?', $from) => [$case];
        }
    }

    /**
     * @param array<string, mixed> $case
     */
    #[DataProvider('examples')]
    public function testExample(array $case): void
    {
        $parser = new CssParser();
        $space = ColorSpaceRegistry::default()->get(\is_string($case['space'] ?? null) ? $case['space'] : '');
        $from = $parser->parse(\is_string($case['from'] ?? null) ? $case['from'] : '');

        if (($case['kind'] ?? null) === 'convert') {
            $result = MissingComponents::convert($from, $space);
        } else {
            $hue = HueInterpolation::from(\is_string($case['hue'] ?? null) ? $case['hue'] : '');
            $to = $parser->parse(\is_string($case['to'] ?? null) ? $case['to'] : '');
            $result = Segment::between($from, $to, new InterpolationMethod($space, $hue))->at(FixtureLoader::number($case['progress'] ?? null));
        }

        $this->assertValue($case, $result);
    }

    /**
     * @param array<string, mixed> $case
     */
    private function assertValue(array $case, ColorValue $result): void
    {
        $expected = $case['expected'] ?? null;
        \assert(\is_array($expected));
        $tolerance = isset($case['abs']) ? Tolerance::absolute(FixtureLoader::number($case['abs'])) : FixtureLoader::reference(self::FIXTURE)->tolerance;

        self::assertSame($case['space'], $result->space->id());
        foreach ($expected as $index => $value) {
            if ($value === null) {
                self::assertNull($result->coordinates[$index], (string) $index);
                continue;
            }

            self::assertNotNull($result->coordinates[$index], (string) $index);
            self::assertFloatClose(FixtureLoader::number($value), $result->coordinates[$index], $tolerance, (string) $index);
        }

        self::assertNotNull($result->alpha);
        self::assertFloatClose(FixtureLoader::number($case['alpha'] ?? null), $result->alpha, $tolerance, 'alpha');
    }
}
