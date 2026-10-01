<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Interpolation;

use Domm98CZ\Color\Interpolation\HueInterpolation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The fix-up rules of CSS Color 4 § 13.5.1–13.5.4, including the boundaries of each condition.
 */
final class HueInterpolationTest extends TestCase
{
    /**
     * @return iterable<string, array{HueInterpolation, float, float, float, float}>
     */
    public static function fixups(): iterable
    {
        // shorter: θ₂ − θ₁ ∈ [−180, 180]
        yield 'shorter, no change' => [HueInterpolation::Shorter, 30.0, 90.0, 30.0, 90.0];
        yield 'shorter, difference exactly 180' => [HueInterpolation::Shorter, 0.0, 180.0, 0.0, 180.0];
        yield 'shorter, difference exactly -180' => [HueInterpolation::Shorter, 180.0, 0.0, 180.0, 0.0];
        yield 'shorter, difference above 180' => [HueInterpolation::Shorter, 10.0, 350.0, 370.0, 350.0];
        yield 'shorter, difference below -180' => [HueInterpolation::Shorter, 350.0, 10.0, 350.0, 370.0];
        // longer: θ₂ − θ₁ ∈ (−360, −180] ∪ [180, 360)
        yield 'longer, 0 < d < 180' => [HueInterpolation::Longer, 30.0, 90.0, 390.0, 90.0];
        yield 'longer, d exactly 180' => [HueInterpolation::Longer, 0.0, 180.0, 0.0, 180.0];
        yield 'longer, d above 180' => [HueInterpolation::Longer, 10.0, 350.0, 10.0, 350.0];
        yield 'longer, -180 < d < 0' => [HueInterpolation::Longer, 90.0, 30.0, 90.0, 390.0];
        yield 'longer, d exactly 0 goes around' => [HueInterpolation::Longer, 60.0, 60.0, 60.0, 420.0];
        yield 'longer, d exactly -180' => [HueInterpolation::Longer, 180.0, 0.0, 180.0, 0.0];
        yield 'longer, d below -180' => [HueInterpolation::Longer, 350.0, 10.0, 350.0, 10.0];
        // increasing: θ₂ − θ₁ ∈ [0, 360)
        yield 'increasing, already increasing' => [HueInterpolation::Increasing, 30.0, 190.0, 30.0, 190.0];
        yield 'increasing, equal' => [HueInterpolation::Increasing, 60.0, 60.0, 60.0, 60.0];
        yield 'increasing, decreasing input' => [HueInterpolation::Increasing, 190.0, 30.0, 190.0, 390.0];
        // decreasing: θ₂ − θ₁ ∈ (−360, 0]
        yield 'decreasing, already decreasing' => [HueInterpolation::Decreasing, 190.0, 30.0, 190.0, 30.0];
        yield 'decreasing, equal' => [HueInterpolation::Decreasing, 60.0, 60.0, 60.0, 60.0];
        yield 'decreasing, increasing input' => [HueInterpolation::Decreasing, 30.0, 190.0, 390.0, 190.0];
    }

    #[DataProvider('fixups')]
    public function testFixup(HueInterpolation $method, float $first, float $second, float $expectedFirst, float $expectedSecond): void
    {
        self::assertSame([$expectedFirst, $expectedSecond], $method->fixup($first, $second));
    }

    public function testKeywordsAreTheCssOnes(): void
    {
        self::assertSame(['shorter', 'longer', 'increasing', 'decreasing'], \array_map(static fn(HueInterpolation $h): string => $h->value, HueInterpolation::cases()));
    }
}
