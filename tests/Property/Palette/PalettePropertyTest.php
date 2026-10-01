<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Property\Palette;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Difference\DeltaE;
use Domm98CZ\Color\Gamut\GamutMappers;
use Domm98CZ\Color\Palette\Catalogs;
use Domm98CZ\Color\Palette\Scales;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Support\SeededRandom;
use PHPUnit\Framework\TestCase;

/**
 * Scales and catalog search over seeded random colors.
 */
final class PalettePropertyTest extends TestCase
{
    private const int ITERATIONS = 40;

    private static function color(SeededRandom $random): Color
    {
        return Color::fromSrgb($random->float(0.0, 1.0), $random->float(0.0, 1.0), $random->float(0.0, 1.0));
    }

    public function testMappedLightnessStepsAreInGamut(): void
    {
        $random = SeededRandom::fromEnvironment();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $scale = Scales::lightnessSteps(ColorSpaces::oklch(), [0.95, 0.75, 0.5, 0.25, 0.05])->generate(self::color($random), GamutMappers::cssColor4());

            foreach ($scale->palette->colors() as $color) {
                self::assertTrue($color->isInGamut(), $random->describe());
            }
        }
    }

    public function testNearestIsTheMinimum(): void
    {
        $random = SeededRandom::fromEnvironment();
        $catalog = Catalogs::cssNamed();
        $metric = DeltaE::ciede2000();

        for ($i = 0; $i < 10; $i++) {
            $color = self::color($random);
            $nearest = $catalog->nearest($color, $metric);

            foreach ($catalog->entries() as $entry) {
                self::assertLessThanOrEqual($metric->difference($color, $entry->color)->value, $nearest->difference->value, $random->describe() . ' ' . $entry->name);
            }
        }
    }
}
