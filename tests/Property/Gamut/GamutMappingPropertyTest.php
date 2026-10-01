<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Property\Gamut;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Gamut\Contract\GamutMapper;
use Domm98CZ\Color\Gamut\GamutMappers;
use Domm98CZ\Color\Math\Angle;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Tests\Support\SeededRandom;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Invariants of gamut mapping over seeded random OKLCh colors, most of them out of gamut.
 */
final class GamutMappingPropertyTest extends TestCase
{
    private const int ITERATIONS = 150;

    /**
     * @return iterable<string, array{GamutMapper, ColorSpace, bool}>
     */
    public static function cases(): iterable
    {
        foreach (['srgb' => ColorSpaces::srgb(), 'display-p3' => ColorSpaces::displayP3(), 'rec2020' => ColorSpaces::rec2020()] as $id => $space) {
            yield 'css color 4 → ' . $id => [GamutMappers::cssColor4(), $space, true];
            yield 'ray trace → ' . $id => [GamutMappers::rayTrace(), $space, true];
            yield 'chroma reduction → ' . $id => [GamutMappers::chromaReduction(), $space, true];
            yield 'clip → ' . $id => [GamutMappers::clip(), $space, false];
        }
    }

    #[DataProvider('cases')]
    public function testMappedColorsAreInGamutAndStable(GamutMapper $mapper, ColorSpace $destination, bool $keepsLightnessAndHue): void
    {
        $random = SeededRandom::fromEnvironment();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $origin = Color::fromOklch($random->float(0.02, 0.98), $random->float(0.0, 0.5), $random->float(0.0, 360.0));
            $result = $mapper->map($origin, $destination);
            $context = $random->describe() . ' ' . $origin->toCss();

            self::assertTrue($result->color->isInGamut(null, Tolerance::exact()), $context . ' strictly in gamut');
            self::assertSame($origin->isInGamut($destination), $result->wasInGamut, $context);

            $again = $mapper->map($result->color, $destination);
            self::assertTrue($again->wasInGamut, $context . ' idempotent');
            self::assertTrue($again->color->isCloseTo($result->color, Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB)), $context . ' idempotent');

            if ($keepsLightnessAndHue && !$result->wasInGamut) {
                [$lightness, $chroma, $hue] = $result->color->to(ColorSpaces::oklch())->coordinates();
                self::assertEqualsWithDelta($origin->coordinates()[0], $lightness, Tolerances::OKLCH_JND, $context . ' lightness');

                if ($chroma !== null && $chroma > Tolerances::OKLCH_JND && $hue !== null) {
                    self::assertLessThanOrEqual(\rad2deg(Tolerances::OKLCH_JND / $chroma), Angle::distanceDegrees($origin->coordinates()[2] ?? 0.0, $hue), $context . ' hue');
                }
            }
        }
    }
}
