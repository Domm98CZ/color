<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Property\Harmony;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Harmony\Contract\HueWheel;
use Domm98CZ\Color\Harmony\HueWheels;
use Domm98CZ\Color\Math\Angle;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Tests\Support\SeededRandom;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every wheel: moving a chromatic color to an angle puts it at that angle, and a full turn
 * returns the color.
 */
final class HueWheelPropertyTest extends TestCase
{
    private const int ITERATIONS = 150;

    /**
     * @return iterable<string, array{HueWheel}>
     */
    public static function wheels(): iterable
    {
        yield 'oklch' => [HueWheels::oklch()];
        yield 'cielch' => [HueWheels::cieLch()];
        yield 'hsl' => [HueWheels::hsl()];
        yield 'ryb' => [HueWheels::ryb()];
    }

    #[DataProvider('wheels')]
    public function testColorAtAndHueOfAreInverse(HueWheel $wheel): void
    {
        $random = SeededRandom::fromEnvironment();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $template = Color::fromHsl($random->float(0.0, 360.0), $random->float(20.0, 100.0), $random->float(20.0, 80.0));
            $angle = $random->float(0.0, 360.0);
            $moved = $wheel->colorAt($angle, $template);
            $context = $random->describe() . ' ' . $template->toCss() . ' → ' . $angle;

            self::assertLessThanOrEqual(Tolerances::ANGLE_DEGREES, Angle::distanceDegrees($angle, $wheel->hueOf($moved) ?? \NAN), $context);
            self::assertTrue($wheel->colorAt(($wheel->hueOf($template) ?? 0.0) + 360.0, $template)->isCloseTo($template, Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB)), $context . ' full turn');
        }
    }
}
