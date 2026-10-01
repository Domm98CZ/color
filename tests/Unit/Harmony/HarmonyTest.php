<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Harmony;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Harmony\Exception\InvalidHarmonyException;
use Domm98CZ\Color\Harmony\Harmony;
use Domm98CZ\Color\Harmony\HarmonyScheme;
use Domm98CZ\Color\Harmony\HueWheels;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Provenance\WarningCode;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HarmonyTest extends TestCase
{
    use FloatAssertions;

    /**
     * @return iterable<string, array{HarmonyScheme, string, list<float>}>
     */
    public static function schemes(): iterable
    {
        yield 'complementary' => [HarmonyScheme::complementary(), 'complementary', [0.0, 180.0]];
        yield 'split-complementary' => [HarmonyScheme::splitComplementary(), 'split-complementary[spread=30]', [0.0, 150.0, 210.0]];
        yield 'split-complementary 22.5' => [HarmonyScheme::splitComplementary(22.5), 'split-complementary[spread=22.5]', [0.0, 157.5, 202.5]];
        yield 'analogous default' => [HarmonyScheme::analogous(), 'analogous[count=3,step=30]', [-30.0, 0.0, 30.0]];
        yield 'analogous 5' => [HarmonyScheme::analogous(5, 20.0), 'analogous[count=5,step=20]', [-40.0, -20.0, 0.0, 20.0, 40.0]];
        yield 'analogous even' => [HarmonyScheme::analogous(4, 15.0), 'analogous[count=4,step=15]', [-15.0, 0.0, 15.0, 30.0]];
        yield 'analogous one' => [HarmonyScheme::analogous(1), 'analogous[count=1,step=30]', [0.0]];
        yield 'triadic' => [HarmonyScheme::triadic(), 'triadic', [0.0, 120.0, 240.0]];
        yield 'tetradic' => [HarmonyScheme::tetradic(), 'tetradic[offset=60]', [0.0, 60.0, 180.0, 240.0]];
        yield 'tetradic negative' => [HarmonyScheme::tetradic(-45.0), 'tetradic[offset=-45]', [0.0, -45.0, 180.0, 135.0]];
        yield 'square' => [HarmonyScheme::square(), 'square', [0.0, 90.0, 180.0, 270.0]];
        yield 'custom' => [HarmonyScheme::custom('mine', [0.0, 45.0]), 'mine', [0.0, 45.0]];
    }

    /**
     * @param list<float> $offsets
     */
    #[DataProvider('schemes')]
    public function testSchemes(HarmonyScheme $scheme, string $name, array $offsets): void
    {
        self::assertSame($name, $scheme->name);
        self::assertSame($offsets, $scheme->offsets);
    }

    /**
     * @return iterable<string, array{\Closure(): mixed, string}>
     */
    public static function invalidSchemes(): iterable
    {
        yield 'spread 0' => [static fn() => HarmonyScheme::splitComplementary(0.0), 'spread must be in (0, 180)'];
        yield 'spread 180' => [static fn() => HarmonyScheme::splitComplementary(180.0), 'spread must be in (0, 180)'];
        yield 'spread NaN' => [static fn() => HarmonyScheme::splitComplementary(\NAN), 'spread must be in (0, 180)'];
        yield 'analogous 0' => [static fn() => HarmonyScheme::analogous(0), 'at least one color, 0 given'];
        yield 'analogous step INF' => [static fn() => HarmonyScheme::analogous(3, \INF), 'step must be finite'];
        yield 'tetradic NaN' => [static fn() => HarmonyScheme::tetradic(\NAN), 'offset must be finite'];
        yield 'custom empty' => [static fn() => HarmonyScheme::custom('x', []), 'at least one offset'];
        yield 'custom INF' => [static fn() => HarmonyScheme::custom('x', [0.0, \INF]), 'must be finite'];
    }

    /**
     * @param \Closure(): mixed $call
     */
    #[DataProvider('invalidSchemes')]
    public function testInvalidSchemes(\Closure $call, string $message): void
    {
        $this->expectException(InvalidHarmonyException::class);
        $this->expectExceptionMessage($message);
        $call();
    }

    public function testComplementaryOnDifferentWheels(): void
    {
        $base = Color::fromHex('#3366cc');

        $hsl = Harmony::complementary($base, HueWheels::hsl());
        self::assertSame('#cc9933', $hsl->members[1]->color->toHex());
        self::assertTrue($hsl->members[0]->color->isCloseTo($base, Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB)), 'offset 0 is the base');

        $oklch = Harmony::complementary($base, HueWheels::oklch());
        $hue = $base->to(ColorSpaces::oklch())->coordinate('h') ?? \NAN;
        self::assertFloatClose(\fmod($hue + 180.0, 360.0), $oklch->members[1]->angle ?? \NAN, Tolerance::absolute(Tolerances::ANGLE_DEGREES));
        self::assertFalse($oklch->members[1]->inGamut, 'the OKLCh complement of #3366cc is outside sRGB');
        self::assertFalse($oklch->members[1]->color->isCloseTo($hsl->members[1]->color, Tolerance::absolute(Tolerances::DISTINCT)), 'wheels differ');
        self::assertSame('hue-wheel-oklch', $oklch->wheel->name);
    }

    public function testMembersAreInTheBaseSpaceAndReportTheGamut(): void
    {
        $set = Harmony::triadic(Color::fromHex('#3366cc'), HueWheels::cieLch());

        self::assertSame('srgb', $set->gamut->id());
        self::assertSame(['srgb', 'srgb', 'srgb'], \array_map(static fn(Color $c): string => $c->space()->id(), $set->colors()));
        self::assertSame([true, true, false], \array_map(static fn($m): bool => $m->inGamut, $set->members));
        self::assertFalse($set->allInGamut());
        self::assertTrue(Harmony::triadic(Color::fromHex('#3366cc'), HueWheels::cieLch(), ColorSpaces::rec2020())->allInGamut());
        self::assertTrue($set->diagnostics->isEmpty());
    }

    public function testFacadeShortcuts(): void
    {
        $base = Color::fromHsl(20.0, 60.0, 50.0);
        $wheel = HueWheels::hsl();

        self::assertSame([20.0, 170.0, 230.0], self::angles(Harmony::splitComplementary($base, $wheel)->members));
        self::assertSame([350.0, 20.0, 50.0], self::angles(Harmony::analogous($base, $wheel)->members));
        self::assertSame([10.0, 20.0, 30.0, 40.0], self::angles(Harmony::analogous($base, $wheel, 4, 10.0)->members));
        self::assertSame([20.0, 140.0, 260.0], self::angles(Harmony::triadic($base, $wheel)->members));
        self::assertSame([20.0, 50.0, 200.0, 230.0], self::angles(Harmony::tetradic($base, $wheel, 30.0)->members));
        self::assertSame([20.0, 110.0, 200.0, 290.0], self::angles(Harmony::square($base, $wheel)->members));
        self::assertSame('square', Harmony::square($base, $wheel)->scheme->name);
    }

    public function testAnAchromaticBaseHasNoHarmony(): void
    {
        $gray = Color::fromHex('#808080');
        $set = Harmony::triadic($gray, HueWheels::oklch());

        self::assertSame([$gray, $gray, $gray], $set->colors());
        self::assertSame([null, null, null], \array_map(static fn($m): ?float => $m->angle, $set->members));
        self::assertTrue($set->diagnostics->hasWarning(WarningCode::HueUndefined));
        self::assertSame($gray, $set->base);
        self::assertSame(['wheel' => 'hue-wheel-oklch@r1'], $set->diagnostics->warnings[0]->context);
    }

    public function testOfWithAnExplicitScheme(): void
    {
        $set = Harmony::of(Color::fromHsl(20.0, 60.0, 50.0), HueWheels::hsl(), HarmonyScheme::custom('pair', [0.0, 45.0]));

        self::assertSame([20.0, 65.0], self::angles($set->members));
        self::assertSame('pair', $set->scheme->name);
    }

    /**
     * @param list<\Domm98CZ\Color\Harmony\Result\HarmonyMember> $members
     *
     * @return list<float>
     */
    private static function angles(array $members): array
    {
        return \array_map(static fn($member): float => \round($member->angle ?? \NAN, 9), $members);
    }
}
