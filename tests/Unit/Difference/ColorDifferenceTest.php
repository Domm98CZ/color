<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Difference;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Difference\Algorithm\Cie76;
use Domm98CZ\Color\Difference\Algorithm\Ciede2000;
use Domm98CZ\Color\Difference\Algorithm\DeltaEOk;
use Domm98CZ\Color\Difference\Contract\ColorDifferenceMetric;
use Domm98CZ\Color\Difference\DeltaE;
use Domm98CZ\Color\Difference\Exception\InvalidMetricParameterException;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Provenance\WarningCode;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ColorDifferenceTest extends TestCase
{
    use FloatAssertions;

    /**
     * @return iterable<string, array{ColorDifferenceMetric, string}>
     */
    public static function metrics(): iterable
    {
        yield 'cie76' => [DeltaE::cie76(), 'cie76[white=D50]@r1'];
        yield 'ciede2000' => [DeltaE::ciede2000(), 'ciede2000[kL=1,kC=1,kH=1,white=D50]@r1'];
        yield 'ciede2000 textiles' => [DeltaE::ciede2000(2.0, 1.0, 1.5, ColorSpaces::labD65()), 'ciede2000[kL=2,kC=1,kH=1.5,white=D65]@r1'];
        yield 'ok' => [DeltaE::ok(), 'deltae-ok@r1'];
    }

    #[DataProvider('metrics')]
    public function testIdentityAndZeroDifference(ColorDifferenceMetric $metric, string $identifier): void
    {
        $color = Color::fromRgb(51, 102, 204);
        $difference = $metric->difference($color, $color);

        self::assertSame($identifier, $metric->id()->identifier());
        self::assertSame($identifier, $difference->metric->identifier());
        self::assertNotSame([], $metric->id()->sources);
        self::assertFloatClose(0.0, $difference->value, Tolerances::linearAlgebra());
        self::assertTrue($difference->diagnostics->isEmpty());
    }

    #[DataProvider('metrics')]
    public function testTranslucentInputIsReported(ColorDifferenceMetric $metric, string $identifier): void
    {
        $difference = $metric->difference(Color::fromRgba(0, 0, 0, 0.5), Color::fromRgb(255, 255, 255));

        self::assertTrue($difference->diagnostics->hasWarning(WarningCode::AlphaIgnored), $identifier);
        self::assertSame(['referenceAlpha' => 0.5, 'sampleAlpha' => 1.0], $difference->diagnostics->warnings[0]->context);
        self::assertTrue($metric->difference(Color::fromRgb(0, 0, 0), Color::fromRgba(0, 0, 0, 0.25))->diagnostics->hasWarning(WarningCode::AlphaIgnored));
    }

    public function testCie76IsTheEuclideanDistanceInLab(): void
    {
        $difference = DeltaE::cie76()->difference(Color::fromLab(50, 0, 0), Color::fromLab(53, 4, 0));

        self::assertFloatClose(5.0, $difference->value, Tolerances::linearAlgebra());
        self::assertNull($difference->components);
        self::assertFloatClose(5.0, DeltaE::cie76()->difference(Color::fromLab(50, 0, 0), Color::fromLab(50, 0, -5))->value, Tolerances::linearAlgebra());
    }

    public function testMissingComponentsCountAsZero(): void
    {
        $missing = Color::of(ColorSpaces::lab(), [50.0, null, 0.0]);

        self::assertFloatClose(0.0, DeltaE::cie76()->difference($missing, Color::fromLab(50, 0, 0))->value, Tolerances::linearAlgebra());
        self::assertFloatClose(0.0, DeltaE::ok()->difference(Color::of(ColorSpaces::oklab(), [0.5, null, null]), Color::fromOklab(0.5, 0, 0))->value, Tolerances::linearAlgebra());
    }

    public function testTheWhiteOfTheLabSpaceMatters(): void
    {
        $a = Color::fromRgb(200, 50, 50);
        $b = Color::fromRgb(50, 200, 50);

        $d50 = DeltaE::cie76()->difference($a, $b)->value;
        $d65 = DeltaE::cie76(ColorSpaces::labD65())->difference($a, $b)->value;
        self::assertNotEqualsWithDelta($d50, $d65, Tolerances::DISTINCT);
        self::assertSame($d65, (new Cie76(ColorSpaces::labD65()))->difference($a, $b)->value);
    }

    public function testDeltaEOk(): void
    {
        $a = Color::fromOklab(0.5, 0.0, 0.0);
        $b = Color::fromOklab(0.6, 0.03, 0.04);

        self::assertFloatClose(\sqrt(0.0125), DeltaE::ok()->difference($a, $b)->value, Tolerances::linearAlgebra());
        self::assertNull(DeltaE::ok()->difference($a, $b)->components);
        self::assertStringContainsString('20.3', (string) (new DeltaEOk())->id()->sources[0]->section);
    }

    public function testCiede2000Components(): void
    {
        // Sharma et al. 2005, pair 1: ΔL′ = 0, C′1 = 79.8200, C′2 = 82.7485
        $components = DeltaE::ciede2000()->difference(Color::fromLab(50, 2.6772, -79.7751), Color::fromLab(50, 0, -82.7485))->components;

        self::assertNotNull($components);
        self::assertSame(0.0, $components->deltaL);
        self::assertFloatClose(82.7485 - 79.8200, $components->deltaC, Tolerance::absolute(2 * Tolerances::printedDecimals(4)));
    }

    public function testCiede2000ParametricFactorsWeightTheTerms(): void
    {
        $a = Color::fromLab(50, 20, 20);
        $lightness = Color::fromLab(60, 20, 20);

        self::assertFloatClose(
            DeltaE::ciede2000()->difference($a, $lightness)->value / 2.0,
            DeltaE::ciede2000(kL: 2.0)->difference($a, $lightness)->value,
            Tolerances::linearAlgebra(),
        );

        $chroma = Color::fromLab(50, 30, 30);
        self::assertLessThan(DeltaE::ciede2000()->difference($a, $chroma)->value, DeltaE::ciede2000(kC: 2.0)->difference($a, $chroma)->value);

        $hue = Color::fromLab(50, 20, -20);
        self::assertLessThan(DeltaE::ciede2000()->difference($a, $hue)->value, DeltaE::ciede2000(kH: 2.0)->difference($a, $hue)->value);
    }

    public function testFactoriesMatchConstructors(): void
    {
        self::assertEquals(new Cie76(), DeltaE::cie76());
        self::assertEquals(new Ciede2000(), DeltaE::ciede2000());
    }

    /**
     * @return iterable<string, array{callable(): ColorDifferenceMetric, string}>
     */
    public static function invalidParameters(): iterable
    {
        yield 'kL INF' => [static fn(): ColorDifferenceMetric => new Ciede2000(\INF), 'kL'];
        yield 'kC NaN' => [static fn(): ColorDifferenceMetric => new Ciede2000(1.0, \NAN), 'kC'];
        yield 'kH 0' => [static fn(): ColorDifferenceMetric => new Ciede2000(1.0, 1.0, 0.0), 'kH'];
    }

    /**
     * @param callable(): ColorDifferenceMetric $create
     */
    #[DataProvider('invalidParameters')]
    public function testParametersAreValidated(callable $create, string $name): void
    {
        $this->expectException(InvalidMetricParameterException::class);
        $this->expectExceptionMessage(\sprintf('The parameter %s must be a finite number > 0', $name));

        $create();
    }

    /**
     * Boundary cases of CIEDE2000 (hue differences of exactly ±180°, hue sums of exactly 360°,
     * a′ < 0 with b = 0, a′ = 0). Expected values from an independent implementation of Sharma et al.
     * (2005) (not kept in the repository).
     *
     * @return iterable<string, array{list<float>, list<float>, float}>
     */
    public static function ciede2000Boundaries(): iterable
    {
        yield 'Δh′ = +180° exactly' => [[50.0, 0.0, 20.0], [50.0, 0.0, -30.0], 36.14984928081313];
        yield 'Δh′ = −180° exactly' => [[50.0, 0.0, -30.0], [50.0, 0.0, 20.0], 36.14984928081313];
        yield 'h′₁ + h′₂ = 360°, |Δh′| > 180°' => [[50.0, 10.0, 20.0], [50.0, 10.0, -20.0], 27.32395427003815];
        yield 'h′₁ + h′₂ = 360°, |Δh′| > 180°, ΔC′ ≠ 0' => [[50.0, 5.0, 20.0], [50.0, 10.0, -40.0], 36.0921238777419];
        yield 'h′₁ + h′₂ > 360°, |Δh′| > 180°' => [[50.0, 0.0, 20.0], [50.0, 5.209445330007912, -29.54423259036624], 33.81755809571828];
        yield 'a′ < 0, b = 0 (h′ = 180°)' => [[50.0, -10.0, 0.0], [52.0, -8.0, 3.0], 3.749953704471797];
        yield 'a′ = 0 (h′ = 90°)' => [[50.0, 0.0, 1.0], [50.0, 0.0, 5.0], 3.5242290748898677];
    }

    /**
     * @param list<float> $reference
     * @param list<float> $sample
     */
    #[DataProvider('ciede2000Boundaries')]
    public function testCiede2000Boundaries(array $reference, array $sample, float $expected): void
    {
        self::assertFloatClose($expected, DeltaE::ciede2000()->difference(Color::fromLab(...$reference), Color::fromLab(...$sample))->value, Tolerances::linearAlgebra());
    }

    public function testMissingLightnessCountsAsZero(): void
    {
        self::assertSame(0.0, DeltaE::cie76()->difference(Color::of(ColorSpaces::lab(), [null, 0.0, 0.0]), Color::fromLab(0, 0, 0))->value);
    }

    public function testCiede2000CitesTheStandardAndThePaper(): void
    {
        self::assertSame(['CIE 142 Improvement to industrial colour-difference evaluation', 'G. Sharma, W. Wu, E. N. Dalal: The CIEDE2000 color-difference formula: Implementation notes, supplementary test data, and mathematical observations'], \array_map(static fn($s): string => $s->title, DeltaE::ciede2000()->id()->sources));
    }
}
