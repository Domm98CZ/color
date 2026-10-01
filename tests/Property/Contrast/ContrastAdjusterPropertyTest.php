<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Property\Contrast;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Contrast\Contrast;
use Domm98CZ\Color\Contrast\ContrastAdjuster;
use Domm98CZ\Color\Operation\LightnessModel;
use Domm98CZ\Color\Provenance\WarningCode;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Record\Oklch;
use Domm98CZ\Color\Tests\Support\SeededRandom;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\TestCase;

/**
 * Invariants of the contrast lightness search over seeded random colors and targets.
 */
final class ContrastAdjusterPropertyTest extends TestCase
{
    private const int ITERATIONS = 150;

    /**
     * A result that is chromatic and not an extreme keeps the input hue. Gamut mapping moves a
     * color by up to one JND and 8-bit quantisation by up to half a step per channel (about
     * 2 / 255 in OKLab units at most); both move the hue by at most that distance divided by the
     * chroma, in radians. Checked only above this chroma, where the bound is meaningful.
     */
    private const float HUE_CHECK_MIN_CHROMA = 0.05;

    public function testInvariants(): void
    {
        $random = SeededRandom::fromEnvironment();
        $wcag = Contrast::wcag2();
        $adjuster = new ContrastAdjuster($wcag, LightnessModel::OklchL);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = Color::fromOklch($random->float(0.05, 0.95), $random->float(0.0, 0.3), $random->float(0.0, 360.0));
            $fixed = Color::fromSrgb($random->float(0.0, 1.0), $random->float(0.0, 1.0), $random->float(0.0, 1.0));
            $target = $random->int(0, 3) === 0 ? $random->float(1.0, 25.0) : [3.0, 4.5, 7.0][$random->int(0, 2)];
            $context = $random->describe() . \sprintf(' input %s fixed %s target %s', $input->toCss(), $fixed->toCss(), $target);

            $result = $adjuster->adjustForeground($input, $fixed, $target);

            self::assertSame($result->contrast->value >= $target, $result->reachable, $context);
            self::assertTrue($result->color->isInGamut(ColorSpaces::srgb()), $context);
            self::assertSame($wcag->measure(Color::fromHex($result->color->toHex()), $fixed)->value, $result->contrast->value, $context . ' hex round trip');
            self::assertLessThanOrEqual(1.0, \abs($result->lightnessChange), $context);
            self::assertSame(1.0, $result->color->alpha(), $context);
            self::assertSame($result->reachable, !$result->diagnostics->hasWarning(WarningCode::ContrastTargetUnreachable), $context);

            $again = $adjuster->adjustForeground($input, $fixed, $target);
            self::assertTrue($again->color->equals($result->color), $context . ' deterministic');
            self::assertSame($result->contrast->value, $again->contrast->value, $context);

            $resultLch = $result->color->in(Oklch::class);
            $extreme = \abs($result->lightnessChange) >= 1.0 - 2 * ContrastAdjuster::PRECISION || $resultLch->l <= 0.0 || $resultLch->l >= 1.0;
            if ($result->reachable && !$extreme && $resultLch->c >= self::HUE_CHECK_MIN_CHROMA && $resultLch->h !== null) {
                $inputHue = $input->in(Oklch::class)->h ?? \NAN;
                $bound = \rad2deg((Tolerances::OKLCH_JND + 2 * Tolerances::SRGB_EIGHT_BIT_STEP) / $resultLch->c);
                self::assertLessThanOrEqual($bound, self::hueDistance($inputHue, $resultLch->h), $context . ' hue kept');
            }
        }
    }

    private static function hueDistance(float $a, float $b): float
    {
        return \abs(\fmod(\abs($a - $b) + 540.0, 360.0) - 180.0);
    }
}
