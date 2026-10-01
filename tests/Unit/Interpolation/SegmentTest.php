<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Interpolation;

use Domm98CZ\Color\Interpolation\Exception\InvalidInterpolationException;
use Domm98CZ\Color\Interpolation\HueInterpolation;
use Domm98CZ\Color\Interpolation\InterpolationMethod;
use Domm98CZ\Color\Interpolation\Segment;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\ColorValue;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SegmentTest extends TestCase
{
    use FloatAssertions;

    public function testEndpointsOfOpaqueColorsAreExact(): void
    {
        $a = new ColorValue(ColorSpaces::srgb(), [0.1, 0.2, 0.3]);
        $b = new ColorValue(ColorSpaces::srgb(), [0.7, 0.8, 0.9]);
        $segment = Segment::between($a, $b, new InterpolationMethod(ColorSpaces::srgb()));

        self::assertSame([0.1, 0.2, 0.3], $segment->at(0.0)->coordinates);
        self::assertSame([0.7, 0.8, 0.9], $segment->at(1.0)->coordinates);
        self::assertSame(1.0, $segment->at(0.5)->alpha);
    }

    public function testEqualColorsWithEqualTranslucentAlphaStayExact(): void
    {
        // Premultiplication cancels out and is skipped: no x·α/α rounding.
        // (0.1 · 0.7) / 0.7 = 0.09999999999999999 in binary64
        $a = new ColorValue(ColorSpaces::oklab(), [0.1, 0.2, -0.07], 0.7);
        $result = Segment::between($a, $a, new InterpolationMethod(ColorSpaces::oklab()))->at(0.37);

        self::assertSame($a->coordinates, $result->coordinates);
        self::assertSame(0.7, $result->alpha);
    }

    public function testPremultipliedInterpolation(): void
    {
        // Transparent red towards opaque blue: the red never shows (premultiplied, § 13.4).
        $a = new ColorValue(ColorSpaces::srgb(), [1.0, 0.0, 0.0], 0.0);
        $b = new ColorValue(ColorSpaces::srgb(), [0.0, 0.0, 1.0], 1.0);
        $middle = Segment::between($a, $b, new InterpolationMethod(ColorSpaces::srgb()))->at(0.5);

        self::assertSame([0.0, 0.0, 1.0], $middle->coordinates);
        self::assertSame(0.5, $middle->alpha);
    }

    public function testZeroAlphaIsNotUnpremultiplied(): void
    {
        // "If the interpolated alpha value is zero, the un-premultiplied value is the premultiplied value."
        $a = new ColorValue(ColorSpaces::srgb(), [1.0, 0.5, 0.25], 0.0);
        $b = new ColorValue(ColorSpaces::srgb(), [0.0, 0.0, 1.0], 1.0);

        $start = Segment::between($a, $b, new InterpolationMethod(ColorSpaces::srgb()))->at(0.0);
        self::assertSame([0.0, 0.0, 0.0], $start->coordinates);
        self::assertSame(0.0, $start->alpha);
    }

    public function testTheHueIsNotPremultiplied(): void
    {
        $a = new ColorValue(ColorSpaces::oklch(), [0.6, 0.2, 40.0], 0.2);
        $b = new ColorValue(ColorSpaces::oklch(), [0.6, 0.2, 80.0], 0.8);
        $middle = Segment::between($a, $b, new InterpolationMethod(ColorSpaces::oklch()))->at(0.5);

        self::assertNotNull($middle->coordinates[2]);
        // premultiplied hue would give (40·0.2 + 80·0.8)/2/0.5 = 72
        self::assertFloatClose(60.0, $middle->coordinates[2], Tolerances::linearAlgebra());
        self::assertNotNull($middle->coordinates[0]);
        self::assertFloatClose(0.6, $middle->coordinates[0], Tolerances::linearAlgebra());
    }

    public function testMissingInBothStaysMissing(): void
    {
        $a = new ColorValue(ColorSpaces::oklch(), [0.5, null, null], null);
        $b = new ColorValue(ColorSpaces::oklch(), [0.7, null, 120.0], null);
        $middle = Segment::between($a, $b, new InterpolationMethod(ColorSpaces::oklch()))->at(0.5);

        self::assertSame([0.6, null, 120.0], $middle->coordinates);
        self::assertNull($middle->alpha);
    }

    public function testAMissingAlphaTakesTheOtherAndPremultipliesWithIt(): void
    {
        $a = new ColorValue(ColorSpaces::srgb(), [1.0, 0.0, 0.0], null);
        $b = new ColorValue(ColorSpaces::srgb(), [0.0, 0.0, 1.0], 0.5);
        $middle = Segment::between($a, $b, new InterpolationMethod(ColorSpaces::srgb()))->at(0.5);

        self::assertSame([0.5, 0.0, 0.5], $middle->coordinates);
        self::assertSame(0.5, $middle->alpha);
    }

    public function testTheResultingHueIsNormalised(): void
    {
        $a = new ColorValue(ColorSpaces::oklch(), [0.5, 0.1, 300.0]);
        $b = new ColorValue(ColorSpaces::oklch(), [0.5, 0.1, 60.0]);
        $segment = Segment::between($a, $b, new InterpolationMethod(ColorSpaces::oklch(), HueInterpolation::Increasing));

        self::assertSame(0.0, $segment->at(0.5)->coordinates[2]);
        self::assertSame(60.0, $segment->at(1.0)->coordinates[2]);
    }

    public function testInputHuesOutsideTheCircleAreNormalisedFirst(): void
    {
        // As written by a parser: 400° is 40°, so the shorter arc to 60° is 20° long.
        $a = new ColorValue(ColorSpaces::oklch(), [0.5, 0.1, 400.0]);
        $b = new ColorValue(ColorSpaces::oklch(), [0.5, 0.1, 60.0]);

        self::assertSame(50.0, Segment::between($a, $b, new InterpolationMethod(ColorSpaces::oklch()))->at(0.5)->coordinates[2]);
    }

    public function testColorsAreConvertedToTheInterpolationSpace(): void
    {
        $a = new ColorValue(ColorSpaces::srgb(), [1.0, 1.0, 1.0]);
        $b = new ColorValue(ColorSpaces::srgb(), [0.0, 0.0, 0.0]);
        $middle = Segment::between($a, $b, InterpolationMethod::oklab())->at(0.5);

        self::assertSame('oklab', $middle->space->id());
        self::assertNotNull($middle->coordinates[0]);
        self::assertFloatClose(0.5, $middle->coordinates[0], Tolerances::linearAlgebra());
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function invalidProgress(): iterable
    {
        yield 'negative' => [-0.01];
        yield 'above one' => [1.01];
        yield 'NaN' => [\NAN];
        yield 'infinite' => [\INF];
    }

    #[DataProvider('invalidProgress')]
    public function testRejectsProgressOutsideTheUnitInterval(float $progress): void
    {
        $a = new ColorValue(ColorSpaces::srgb(), [1.0, 1.0, 1.0]);

        $this->expectException(InvalidInterpolationException::class);
        $this->expectExceptionMessage('progress must be in [0, 1]');
        Segment::between($a, $a, InterpolationMethod::oklab())->at($progress);
    }

    public function testMethodDefaults(): void
    {
        $method = InterpolationMethod::oklab();

        self::assertSame('oklab', $method->space->id());
        self::assertSame(HueInterpolation::Shorter, $method->hue);
        self::assertFalse($method->isPolar());
        self::assertTrue((new InterpolationMethod(ColorSpaces::hwb()))->isPolar());
    }
}
