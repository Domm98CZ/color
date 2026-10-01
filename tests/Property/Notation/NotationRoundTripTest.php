<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Property\Notation;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Math\Angle;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Tests\Support\SeededRandom;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Serialised colors parse back to the same color, over seeded random colors in every built-in space.
 */
final class NotationRoundTripTest extends TestCase
{
    private const int ITERATIONS = 200;


    /**
     * @return iterable<string, array{ColorSpace}>
     */
    public static function spaces(): iterable
    {
        foreach (ColorSpaces::all() as $space) {
            yield $space->id() => [$space];
        }
    }

    #[DataProvider('spaces')]
    public function testCssRoundTrip(ColorSpace $space): void
    {
        $random = SeededRandom::fromEnvironment();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $srgb = [$random->float(-0.3, 1.3), $random->float(-0.3, 1.3), $random->float(-0.3, 1.3)];
            $original = Color::fromSrgb($srgb[0], $srgb[1], $srgb[2], $random->float(0.0, 1.0))->to($space);
            $css = $original->toCss();
            $parsed = Color::parse($css);
            $expected = $original->to($parsed->space());
            $context = \sprintf('%s: %s', $random->describe(), $css);

            foreach ($parsed->space()->channels() as $index => $channel) {
                $mine = $expected->coordinates()[$index];
                $theirs = $parsed->coordinates()[$index];

                if ($mine === null || $theirs === null) {
                    self::assertSame($mine, $theirs, $context);
                    continue;
                }

                $difference = $channel->isHue() ? Angle::distanceDegrees($mine, $theirs) : \abs($mine - $theirs);
                self::assertLessThanOrEqual(Tolerances::CSS_ROUND_TRIP, $difference, $context . ' channel ' . $channel->name);
            }

            self::assertEqualsWithDelta($original->alpha(), $parsed->alpha(), Tolerances::printedDecimals(6), $context);
            self::assertSame($css, $parsed->toCss(), $context . ' serialisation is stable');
        }
    }

    #[DataProvider('spaces')]
    public function testJsonRoundTripIsExact(ColorSpace $space): void
    {
        $random = SeededRandom::fromEnvironment();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $original = Color::fromSrgb($random->float(-0.3, 1.3), $random->float(-0.3, 1.3), $random->float(-0.3, 1.3), $random->float(0.0, 1.0))->to($space);

            self::assertTrue(Color::fromJson($original->toJson())->equals($original), $random->describe() . ' ' . $original->toJson());
        }
    }

    public function testHexRoundTripIsExactFor8BitColors(): void
    {
        $random = SeededRandom::fromEnvironment();

        for ($i = 0; $i < self::ITERATIONS * 5; $i++) {
            $original = Color::fromRgba($random->int(0, 255), $random->int(0, 255), $random->int(0, 255), $random->int(0, 255) / 255.0);
            $hex = $original->toHex(true);

            self::assertMatchesRegularExpression('/^#[0-9a-f]{8}$/', $hex);
            self::assertTrue(Color::fromHex($hex)->equals($original), $random->describe() . ' ' . $hex);
        }
    }
}
