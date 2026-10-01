<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Property\Operation;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Interpolation\HueInterpolation;
use Domm98CZ\Color\Math\Angle;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Operation\Adjuster;
use Domm98CZ\Color\Operation\ChromaModel;
use Domm98CZ\Color\Operation\Gradient;
use Domm98CZ\Color\Operation\HueModel;
use Domm98CZ\Color\Operation\LightnessModel;
use Domm98CZ\Color\Operation\Mixer;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Tests\Support\SeededRandom;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Invariants of mixing, gradients and adjustments over seeded random colors.
 */
final class OperationPropertyTest extends TestCase
{
    private const int ITERATIONS = 150;

    private static function randomColor(SeededRandom $random, bool $translucent = true): Color
    {
        return Color::fromSrgb($random->float(0.0, 1.0), $random->float(0.0, 1.0), $random->float(0.0, 1.0), $translucent ? $random->float(0.0, 1.0) : 1.0);
    }

    /**
     * @return iterable<string, array{ColorSpace}>
     */
    public static function rectangularSpaces(): iterable
    {
        yield 'srgb' => [ColorSpaces::srgb()];
        yield 'srgb-linear' => [ColorSpaces::srgbLinear()];
        yield 'oklab' => [ColorSpaces::oklab()];
        yield 'lab' => [ColorSpaces::lab()];
        yield 'xyz-d65' => [ColorSpaces::xyzD65()];
    }

    #[DataProvider('rectangularSpaces')]
    public function testMixingIsSymmetricAndHitsTheEndpoints(ColorSpace $space): void
    {
        $random = SeededRandom::fromEnvironment();
        $mixer = new Mixer($space);
        $tolerance = Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $a = self::randomColor($random);
            $b = self::randomColor($random);
            $weight = $random->float(0.0, 1.0);
            $context = $random->describe() . ' ' . $a->toCss() . ' ' . $b->toCss();

            self::assertTrue($mixer->mix($a, $b, $weight)->isCloseTo($mixer->mix($b, $a, 1.0 - $weight), $tolerance), $context . ' symmetric');
            self::assertTrue($mixer->mix($a, $b, 0.0)->to(ColorSpaces::srgb())->isCloseTo($a, $tolerance), $context . ' start');
            self::assertTrue($mixer->mix($a, $b, 1.0)->to(ColorSpaces::srgb())->isCloseTo($b, $tolerance), $context . ' end');

            $alpha = $mixer->mix($a, $b, $weight)->alpha();
            self::assertGreaterThanOrEqual(\min($a->alpha(), $b->alpha()), $alpha + Tolerances::LINEAR_ALGEBRA_ABSOLUTE, $context . ' alpha');
            self::assertLessThanOrEqual(\max($a->alpha(), $b->alpha()), $alpha - Tolerances::LINEAR_ALGEBRA_ABSOLUTE, $context . ' alpha');
        }
    }

    public function testMixingInAnRgbSpaceStaysInItsGamut(): void
    {
        $random = SeededRandom::fromEnvironment();

        foreach ([ColorSpaces::srgb(), ColorSpaces::srgbLinear()] as $space) {
            $mixer = new Mixer($space);
            for ($i = 0; $i < self::ITERATIONS; $i++) {
                $mixed = $mixer->mix(self::randomColor($random), self::randomColor($random), $random->float(0.0, 1.0));
                self::assertTrue($mixed->isInGamut(ColorSpaces::srgb()), $random->describe() . ' ' . $mixed->toCss());
            }
        }
    }

    public function testShorterHueStaysOnTheShorterArc(): void
    {
        $random = SeededRandom::fromEnvironment();
        $mixer = new Mixer(ColorSpaces::oklch(), HueInterpolation::Shorter);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $a = Color::fromOklch(0.6, 0.1, $random->float(0.0, 360.0));
            $b = Color::fromOklch(0.6, 0.1, $random->float(0.0, 360.0));
            $hue = $mixer->mix($a, $b, $random->float(0.0, 1.0))->coordinate('h');
            $ha = $a->coordinate('h') ?? \NAN;
            $hb = $b->coordinate('h') ?? \NAN;

            self::assertNotNull($hue);
            self::assertEqualsWithDelta(Angle::distanceDegrees($ha, $hb), Angle::distanceDegrees($ha, $hue) + Angle::distanceDegrees($hue, $hb), Tolerances::ANGLE_DEGREES, $random->describe());
        }
    }

    public function testGradientPassesThroughItsStops(): void
    {
        $random = SeededRandom::fromEnvironment();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $middle = self::randomColor($random, false);
            $position = $random->float(0.05, 0.95);
            $gradient = Gradient::of(self::randomColor($random, false), self::randomColor($random, false))->withStop($middle, $position);

            self::assertTrue($gradient->at($position)->to(ColorSpaces::srgb())->isCloseTo($middle, Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB)), $random->describe());
        }
    }

    public function testAdjustmentsAreReversible(): void
    {
        $random = SeededRandom::fromEnvironment();
        $adjust = new Adjuster();
        $tolerance = Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $color = Color::fromSrgb($random->float(0.05, 0.95), $random->float(0.05, 0.95), $random->float(0.05, 0.95));
            $context = $random->describe() . ' ' . $color->toCss();
            $by = $random->float(-0.2, 0.2);
            $degrees = $random->float(-360.0, 360.0);

            self::assertTrue($adjust->shiftLightness($adjust->shiftLightness($color, $by, LightnessModel::OklchL), -$by, LightnessModel::OklchL)->isCloseTo($color, $tolerance), $context . ' OK L');
            self::assertTrue($adjust->shiftLightness($adjust->shiftLightness($color, $by * 100, LightnessModel::CieLabL), -$by * 100, LightnessModel::CieLabL)->isCloseTo($color, $tolerance), $context . ' L*');
            self::assertTrue($adjust->rotateHue($adjust->rotateHue($color, $degrees, HueModel::Oklch), -$degrees, HueModel::Oklch)->isCloseTo($color, $tolerance), $context . ' hue');
            self::assertTrue($adjust->scaleChroma($color, 1.0, ChromaModel::CieLch)->isCloseTo($color, $tolerance), $context . ' chroma × 1');
            self::assertTrue($adjust->rotateHue($color, 360.0, HueModel::Hsl)->isCloseTo($color, $tolerance), $context . ' 360°');
        }
    }

    /**
     * @return iterable<string, array{string, ColorSpace}>
     */
    public static function relativeIdentities(): iterable
    {
        yield 'rgb' => ['rgb(from %s r g b / alpha)', ColorSpaces::srgb()];
        yield 'hsl' => ['hsl(from %s h s l / alpha)', ColorSpaces::hsl()];
        yield 'hwb' => ['hwb(from %s h w b / alpha)', ColorSpaces::hwb()];
        yield 'lab' => ['lab(from %s l a b / alpha)', ColorSpaces::lab()];
        yield 'lch' => ['lch(from %s l c h / alpha)', ColorSpaces::lch()];
        yield 'oklab' => ['oklab(from %s l a b / alpha)', ColorSpaces::oklab()];
        yield 'oklch' => ['oklch(from %s l c h / alpha)', ColorSpaces::oklch()];
        yield 'color(display-p3)' => ['color(from %s display-p3 r g b / alpha)', ColorSpaces::displayP3()];
        yield 'color(xyz-d50)' => ['color(from %s xyz-d50 x y z / alpha)', ColorSpaces::xyzD50()];
    }

    #[DataProvider('relativeIdentities')]
    public function testRelativeColorWithItsOwnKeywordsIsTheConvertedOrigin(string $template, ColorSpace $space): void
    {
        $random = SeededRandom::fromEnvironment();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $origin = self::randomColor($random);
            $relative = Color::parse(\sprintf($template, $origin->toCss()));

            self::assertSame($space->id(), $relative->space()->id());
            self::assertTrue($relative->to(ColorSpaces::srgb())->isCloseTo(Color::parse($origin->toCss()), Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB)), $random->describe() . ' ' . $origin->toCss());
        }
    }
}
