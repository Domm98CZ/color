<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Property\Difference;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Compositing\Algorithm\SourceOver;
use Domm98CZ\Color\Contrast\Contrast;
use Domm98CZ\Color\Difference\Contract\ColorDifferenceMetric;
use Domm98CZ\Color\Difference\DeltaE;
use Domm98CZ\Color\Tests\Support\SeededRandom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Invariants of color differences, contrast and compositing over seeded random colors.
 */
final class DifferenceAndContrastPropertyTest extends TestCase
{
    private const int ITERATIONS = 300;

    /** Symmetry and the triangle inequality hold exactly in real arithmetic; float64 adds ~1e-13. */
    private const float ROUND_OFF = 1e-9;

    /**
     * @return iterable<string, array{ColorDifferenceMetric, bool}>
     */
    public static function metrics(): iterable
    {
        yield 'cie76' => [DeltaE::cie76(), true];
        yield 'ok' => [DeltaE::ok(), true];
        yield 'ciede2000' => [DeltaE::ciede2000(), false];
    }

    #[DataProvider('metrics')]
    public function testMetricInvariants(ColorDifferenceMetric $metric, bool $euclidean): void
    {
        $random = SeededRandom::fromEnvironment();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $a = self::randomColor($random);
            $b = self::randomColor($random);
            $c = self::randomColor($random);
            $context = $random->describe() . ' ' . $metric->id()->identifier();
            $ab = $metric->difference($a, $b)->value;

            self::assertGreaterThanOrEqual(0.0, $ab, $context);
            self::assertEqualsWithDelta(0.0, $metric->difference($a, $a)->value, self::ROUND_OFF, $context);

            if ($euclidean) {
                self::assertEqualsWithDelta($ab, $metric->difference($b, $a)->value, self::ROUND_OFF, $context . ' symmetric');
                self::assertLessThanOrEqual($ab + $metric->difference($b, $c)->value + self::ROUND_OFF, $metric->difference($a, $c)->value, $context . ' triangle');
            }
        }
    }

    public function testCiede2000IsSymmetric(): void
    {
        $random = SeededRandom::fromEnvironment();
        $metric = DeltaE::ciede2000();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $a = self::randomColor($random);
            $b = self::randomColor($random);

            self::assertEqualsWithDelta($metric->difference($a, $b)->value, $metric->difference($b, $a)->value, self::ROUND_OFF, $random->describe());
        }
    }

    public function testWcagContrastRange(): void
    {
        $random = SeededRandom::fromEnvironment();
        $wcag = Contrast::wcag2();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $a = self::randomColor($random);
            $b = self::randomColor($random);
            $ratio = $wcag->measure($a, $b)->value;

            self::assertGreaterThanOrEqual(1.0, $ratio, $random->describe());
            self::assertLessThanOrEqual(21.0 + self::ROUND_OFF, $ratio, $random->describe());
            self::assertSame($ratio, $wcag->measure($b, $a)->value, $random->describe() . ' symmetric');

            // A fully transparent foreground shows the background itself.
            self::assertSame(1.0, $wcag->measure($a->withAlpha(0.0), $b)->value, $random->describe() . ' transparent foreground');
        }
    }

    public function testSourceOverStaysInRange(): void
    {
        $random = SeededRandom::fromEnvironment();
        $operator = new SourceOver();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $source = self::randomColor($random)->withAlpha($random->float(0.0, 1.0));
            $backdrop = self::randomColor($random)->withAlpha($random->float(0.0, 1.0));
            $result = $operator->composite($source, $backdrop);

            self::assertGreaterThanOrEqual(\max($source->alpha(), $backdrop->alpha()) - self::ROUND_OFF, $result->alpha(), $random->describe());
            self::assertSame(1.0, $operator->composite($source, $backdrop->opaque())->alpha(), $random->describe() . ' an opaque backdrop stays exactly opaque');
            self::assertLessThanOrEqual(1.0, $result->alpha(), $random->describe());
            foreach ($result->coordinates() as $value) {
                self::assertNotNull($value);
                self::assertGreaterThanOrEqual(-self::ROUND_OFF, $value, $random->describe() . ' convex combination of in-gamut values');
                self::assertLessThanOrEqual(1.0 + self::ROUND_OFF, $value, $random->describe());
            }
        }
    }

    private static function randomColor(SeededRandom $random): Color
    {
        return Color::fromSrgb($random->float(0.0, 1.0), $random->float(0.0, 1.0), $random->float(0.0, 1.0));
    }
}
