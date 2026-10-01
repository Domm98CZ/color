<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Property\Space;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\SeededRandom;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Round trips through every built-in space, over seeded random colors (in and out of gamut).
 */
final class ConversionPropertyTest extends TestCase
{
    use FloatAssertions;

    private const int ITERATIONS = 300;

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
    public function testInGamutSrgbRoundTrip(ColorSpace $space): void
    {
        $random = SeededRandom::fromEnvironment();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $original = Color::fromSrgb($random->float(0.0, 1.0), $random->float(0.0, 1.0), $random->float(0.0, 1.0));
            $converted = $original->to($space);
            $back = $converted->to(ColorSpaces::srgb());

            self::assertCoordinatesClose($original, $back, self::roundTripTolerance($converted), $random->describe() . ' via ' . $space->id());
            self::assertTrue($back->isInGamut(), $random->describe() . ' an in-gamut color stays in gamut after a round trip');
        }
    }

    /**
     * Out-of-gamut values must survive round trips through every space, including HSL/HWB/HSV
     * (CSS Color 4 defines them over the extended range; no clipping anywhere).
     */
    #[DataProvider('spaces')]
    public function testOutOfGamutRoundTrip(ColorSpace $space): void
    {
        $random = SeededRandom::fromEnvironment();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $original = Color::fromSrgb($random->float(-0.5, 1.5), $random->float(-0.5, 1.5), $random->float(-0.5, 1.5));
            $converted = $original->to($space);

            self::assertCoordinatesClose($original, $converted->to(ColorSpaces::srgb()), self::roundTripTolerance($converted), $random->describe() . ' via ' . $space->id());
        }
    }

    public function testConversionIsIndependentOfThePath(): void
    {
        $random = SeededRandom::fromEnvironment();
        $spaces = ColorSpaces::all();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $original = Color::fromSrgb($random->float(0.0, 1.0), $random->float(0.0, 1.0), $random->float(0.0, 1.0));
            $a = $spaces[$random->int(0, \count($spaces) - 1)];
            $b = $spaces[$random->int(0, \count($spaces) - 1)];

            $viaA = $original->to($a)->to($b)->to(ColorSpaces::srgb());
            $tolerance = \max(self::roundTripTolerance($original->to($a)), self::roundTripTolerance($original->to($b)));

            self::assertCoordinatesClose($original, $viaA, $tolerance, \sprintf('%s srgb -> %s -> %s -> srgb', $random->describe(), $a->id(), $b->id()));
        }
    }

    /**
     * Exact round trips hold unless the conversion made a hue powerless: CSS Color 4 § 4.4.1 then
     * sets a colorfulness ≤ ε to zero, which moves the color by at most that ε (LCh: 0.0015 in Lab
     * units; HSL/HSV: S ≤ 0.001 %; OKLCh: 4e-6). In sRGB units this is below 1e-4.
     */
    private static function roundTripTolerance(Color $converted): float
    {
        return \in_array(null, $converted->coordinates(), true)
            ? Tolerances::POWERLESS_ROUND_TRIP_SRGB
            : Tolerances::CONVERSION_ROUND_TRIP_SRGB;
    }

    private static function assertCoordinatesClose(Color $expected, Color $actual, float $tolerance, string $message): void
    {
        $bound = Tolerance::absolute($tolerance);

        foreach ($expected->coordinates() as $index => $value) {
            self::assertFloatClose($value ?? 0.0, $actual->coordinates()[$index] ?? 0.0, $bound, $message);
        }
    }
}
