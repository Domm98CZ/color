<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Conformance\CssColor4;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Space\ColorSpaceRegistry;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Definition\HslSpace;
use Domm98CZ\Color\Space\Definition\HwbSpace;
use Domm98CZ\Color\Space\Definition\PolarSpace;
use Domm98CZ\Color\Tests\Support\Fixture\FixtureLoader;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Converting sRGB colors into every CSS-defined space reproduces the CSS Color 4 sample code
 * (frozen fixture, generated once from the CSS Color 4 sample code; see its meta.generator).
 */
final class SrgbConversionsTest extends TestCase
{
    use FloatAssertions;

    private const string FIXTURE = 'css-color-4/srgb-conversions.json';

    /** Hue angles are compared separately from other channels. */
    private const float HUE_TOLERANCE_DEGREES = 1e-8;

    /**
     * @return iterable<string, array{list<float>, string, list<float|null>}>
     */
    public static function conversions(): iterable
    {
        foreach (FixtureLoader::reference(self::FIXTURE)->cases as $case) {
            $srgb = \array_map(FixtureLoader::number(...), self::list($case['srgb'] ?? null));
            $expected = $case['expected'] ?? null;
            \assert(\is_array($expected));

            foreach ($expected as $spaceId => $values) {
                $coordinates = \array_map(static fn(mixed $v): ?float => $v === null ? null : FixtureLoader::number($v), self::list($values));

                yield \sprintf('%s -> %s', \json_encode($srgb, \JSON_THROW_ON_ERROR), $spaceId) => [$srgb, (string) $spaceId, $coordinates];
            }
        }
    }

    /**
     * @param list<float> $srgb
     * @param list<float|null> $expected
     */
    #[DataProvider('conversions')]
    public function testConversionMatchesSampleCode(array $srgb, string $spaceId, array $expected): void
    {
        $space = ColorSpaceRegistry::default()->get($spaceId);
        $actual = Color::of(ColorSpaces::srgb(), $srgb)->to($space)->coordinates();
        $expected = self::applyPowerlessRule($spaceId, $expected);
        $tolerance = self::toleranceFor($spaceId);

        foreach ($space->channels() as $index => $channel) {
            $message = \sprintf('%s channel "%s"', $spaceId, $channel->name);

            if ($channel->isHue()) {
                if ($expected[$index] === null) {
                    self::assertNull($actual[$index], $message . ' must be powerless (null).');
                    continue;
                }

                self::assertNotNull($actual[$index], $message . ' must not be powerless.');
                self::assertAngleClose($expected[$index], $actual[$index], self::HUE_TOLERANCE_DEGREES, $message);
                continue;
            }

            self::assertNotNull($expected[$index]);
            self::assertNotNull($actual[$index]);
            self::assertFloatClose($expected[$index], $actual[$index], $tolerance, $message);
        }
    }

    /**
     * CSS Color 4 § 4.4.1 (normative) sets a colorfulness in (0, ε] to 0 when the hue becomes
     * powerless, and adjusts HWB blackness; the sample code does not. Apply the rule to expectations.
     *
     * @param list<float|null> $expected
     *
     * @return list<float|null>
     */
    private static function applyPowerlessRule(string $spaceId, array $expected): array
    {
        $space = ColorSpaceRegistry::default()->get($spaceId);

        if ($space instanceof PolarSpace && $expected[2] === null && $expected[1] !== null && $expected[1] > 0.0 && $expected[1] <= $space->powerlessEpsilon) {
            $expected[1] = 0.0;
        }

        if ($space instanceof HslSpace && $expected[0] === null && $expected[1] !== null && $expected[1] > 0.0 && $expected[1] <= HslSpace::POWERLESS_EPSILON) {
            $expected[1] = 0.0;
        }

        if ($space instanceof HwbSpace && $expected[0] === null && $expected[1] !== null && $expected[2] !== null) {
            $sum = $expected[1] + $expected[2];
            if ($sum >= HwbSpace::POWERLESS_THRESHOLD && $sum < 100.0) {
                $expected[2] = 100.0 - $expected[1];
            }
        }

        return $expected;
    }

    private static function toleranceFor(string $spaceId): Tolerance
    {
        $fixture = FixtureLoader::reference(self::FIXTURE);
        $overrides = $fixture->meta['tolerance_overrides'] ?? [];

        if (\is_array($overrides) && isset($overrides[$spaceId]) && \is_array($overrides[$spaceId])) {
            return Tolerance::absolute(FixtureLoader::number($overrides[$spaceId]['abs'] ?? null));
        }

        return $fixture->tolerance;
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
