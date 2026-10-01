<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Property\Compositing;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Compositing\Algorithm\Composite;
use Domm98CZ\Color\Compositing\BlendMode;
use Domm98CZ\Color\Compositing\Compositor;
use Domm98CZ\Color\Compositing\PorterDuff;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Support\SeededRandom;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\TestCase;

/**
 * Invariants of Porter–Duff compositing and blending over seeded random translucent sRGB colors.
 */
final class CompositingPropertyTest extends TestCase
{
    private const int ITERATIONS = 200;

    private static function color(SeededRandom $random): Color
    {
        return Color::fromSrgb($random->float(0.0, 1.0), $random->float(0.0, 1.0), $random->float(0.0, 1.0), $random->float(0.0, 1.0));
    }

    public function testGeneralFormulaMatchesSimpleSourceOver(): void
    {
        $random = SeededRandom::fromEnvironment();
        $general = new Composite(PorterDuff::SourceOver, BlendMode::Normal);
        $simple = Compositor::sourceOver();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $a = self::color($random);
            $b = self::color($random);
            self::assertTrue($general->composite($a, $b)->isCloseTo($simple->composite($a, $b), Tolerance::absolute(Tolerances::LINEAR_ALGEBRA_ABSOLUTE)), $random->describe());
        }
    }

    public function testSourceOverIsGroupInvariant(): void
    {
        // § 8.1: A + B + C = A + (B + C) = (A + B) + C
        $random = SeededRandom::fromEnvironment();
        $over = Compositor::sourceOver();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            [$a, $b, $c] = [self::color($random), self::color($random), self::color($random)];
            $left = $over->composite($over->composite($a, $b), $c);
            $right = $over->composite($a, $over->composite($b, $c));

            self::assertEqualsWithDelta($left->alpha(), $right->alpha(), Tolerances::LINEAR_ALGEBRA_ABSOLUTE, $random->describe());
            if ($left->alpha() > Tolerances::CONVERSION_ROUND_TRIP_SRGB) {
                self::assertTrue($left->isCloseTo($right, Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB)), $random->describe());
            }
        }
    }

    public function testEveryOperatorAndModeStaysInGamut(): void
    {
        $random = SeededRandom::fromEnvironment();

        foreach (PorterDuff::cases() as $operator) {
            foreach (BlendMode::cases() as $mode) {
                $composite = new Composite($operator, $mode);
                for ($i = 0; $i < 10; $i++) {
                    $result = $composite->composite(self::color($random), self::color($random));
                    $context = $random->describe() . ' ' . $operator->value . ' ' . $mode->value . ' ' . $result->toCss();

                    self::assertGreaterThanOrEqual(0.0, $result->alpha(), $context);
                    self::assertLessThanOrEqual(1.0, $result->alpha(), $context);
                    self::assertTrue($result->isInGamut(ColorSpaces::srgb()), $context);
                }
            }
        }
    }

    public function testSymmetricModesAndOverlayHardLightDuality(): void
    {
        $random = SeededRandom::fromEnvironment();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $b = [$random->float(0.0, 1.0), $random->float(0.0, 1.0), $random->float(0.0, 1.0)];
            $s = [$random->float(0.0, 1.0), $random->float(0.0, 1.0), $random->float(0.0, 1.0)];

            foreach ([BlendMode::Multiply, BlendMode::Screen, BlendMode::Darken, BlendMode::Lighten, BlendMode::Difference, BlendMode::Exclusion] as $mode) {
                self::assertEqualsWithDelta($mode->blend($b, $s), $mode->blend($s, $b), Tolerances::LINEAR_ALGEBRA_ABSOLUTE, $random->describe() . ' ' . $mode->value);
            }

            self::assertEqualsWithDelta(BlendMode::Overlay->blend($b, $s), BlendMode::HardLight->blend($s, $b), Tolerances::LINEAR_ALGEBRA_ABSOLUTE, $random->describe());
            self::assertSame($s, BlendMode::Normal->blend($b, $s));
        }
    }
}
