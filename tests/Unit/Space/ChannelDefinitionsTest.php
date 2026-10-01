<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Space;

use Domm98CZ\Color\Space\Channel;
use Domm98CZ\Color\Space\ChannelKind;
use Domm98CZ\Color\Space\ColorSpaceRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Channel names, kinds and reference ranges of every built-in space. The reference ranges are the
 * CSS Color 4 "percent reference ranges" (and, for RGB, the gamut); they are part of the API.
 */
final class ChannelDefinitionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{string, ChannelKind, float, float}>}>
     */
    public static function definitions(): iterable
    {
        $rgb = [['r', ChannelKind::Number, 0.0, 1.0], ['g', ChannelKind::Number, 0.0, 1.0], ['b', ChannelKind::Number, 0.0, 1.0]];
        $xyz = [['x', ChannelKind::Number, 0.0, 1.0], ['y', ChannelKind::Number, 0.0, 1.0], ['z', ChannelKind::Number, 0.0, 1.0]];
        $lab = [['l', ChannelKind::Number, 0.0, 100.0], ['a', ChannelKind::Number, -125.0, 125.0], ['b', ChannelKind::Number, -125.0, 125.0]];
        $lch = [['l', ChannelKind::Number, 0.0, 100.0], ['c', ChannelKind::Number, 0.0, 150.0], ['h', ChannelKind::Hue, 0.0, 360.0]];

        yield 'xyz-d65' => ['xyz-d65', $xyz];
        yield 'xyz-d50' => ['xyz-d50', $xyz];

        foreach (['srgb', 'srgb-linear', 'display-p3', 'display-p3-linear', 'a98-rgb', 'a98-rgb-linear', 'prophoto-rgb', 'prophoto-rgb-linear', 'rec2020', 'rec2020-linear'] as $id) {
            yield $id => [$id, $rgb];
        }

        yield 'hsl' => ['hsl', [['h', ChannelKind::Hue, 0.0, 360.0], ['s', ChannelKind::Percentage, 0.0, 100.0], ['l', ChannelKind::Percentage, 0.0, 100.0]]];
        yield 'hwb' => ['hwb', [['h', ChannelKind::Hue, 0.0, 360.0], ['w', ChannelKind::Percentage, 0.0, 100.0], ['b', ChannelKind::Percentage, 0.0, 100.0]]];
        yield 'hsv' => ['hsv', [['h', ChannelKind::Hue, 0.0, 360.0], ['s', ChannelKind::Percentage, 0.0, 100.0], ['v', ChannelKind::Percentage, 0.0, 100.0]]];
        yield 'lab' => ['lab', $lab];
        yield 'lab-d65' => ['lab-d65', $lab];
        yield 'lch' => ['lch', $lch];
        yield 'lch-d65' => ['lch-d65', $lch];
        yield 'oklab' => ['oklab', [['l', ChannelKind::Number, 0.0, 1.0], ['a', ChannelKind::Number, -0.4, 0.4], ['b', ChannelKind::Number, -0.4, 0.4]]];
        yield 'oklch' => ['oklch', [['l', ChannelKind::Number, 0.0, 1.0], ['c', ChannelKind::Number, 0.0, 0.4], ['h', ChannelKind::Hue, 0.0, 360.0]]];
    }

    /**
     * @param list<array{string, ChannelKind, float, float}> $expected
     */
    #[DataProvider('definitions')]
    public function testChannels(string $spaceId, array $expected): void
    {
        $channels = ColorSpaceRegistry::default()->get($spaceId)->channels();

        self::assertSame(
            $expected,
            \array_map(static fn(Channel $c): array => [$c->name, $c->kind, $c->referenceMin, $c->referenceMax], $channels),
        );

        foreach ($channels as $channel) {
            self::assertNotSame('', \trim($channel->label));
            self::assertSame($channel->kind === ChannelKind::Hue, $channel->isHue());
        }
    }

    public function testEveryBuiltInSpaceIsCovered(): void
    {
        $covered = \array_map(static fn(array $args): string => $args[0], \iterator_to_array(self::definitions()));

        self::assertEqualsCanonicalizing(
            \array_map(static fn($space): string => $space->id(), ColorSpaceRegistry::default()->all()),
            \array_values($covered),
        );
    }
}
