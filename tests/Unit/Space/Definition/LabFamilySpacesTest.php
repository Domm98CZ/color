<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Space\Definition;

use Domm98CZ\Color\Colorimetry\WhitePoints;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Definition\LabSpace;
use Domm98CZ\Color\Space\Definition\PolarSpace;
use Domm98CZ\Color\Space\Exception\InvalidColorSpaceDefinitionException;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\TestCase;

/**
 * CIE Lab / LCh and OKLab / OKLCh.
 */
final class LabFamilySpacesTest extends TestCase
{
    use FloatAssertions;

    public function testLabOfTheWhiteIs100AndNeutral(): void
    {
        $lab = ColorSpaces::lab()->fromBase(WhitePoints::d50()->xyz->toList());

        self::assertFloatClose(100.0, $lab[0] ?? \NAN, Tolerances::linearAlgebra());
        self::assertFloatClose(0.0, $lab[1] ?? \NAN, Tolerances::linearAlgebra());
        self::assertFloatClose(0.0, $lab[2] ?? \NAN, Tolerances::linearAlgebra());
    }

    public function testLabOfBlackIsZero(): void
    {
        self::assertSame([0.0, 0.0, 0.0], ColorSpaces::lab()->fromBase([0.0, 0.0, 0.0]));
        self::assertSame([0.0, 0.0, 0.0], ColorSpaces::lab()->toBase([0.0, 0.0, 0.0]));
    }

    public function testLabIsContinuousAtEpsilon(): void
    {
        $lab = ColorSpaces::lab();
        $white = WhitePoints::d50()->xyz;
        // Y/Yn at ε = 216/24389 is L* = κε = 8; both branches of f(t) meet there.
        $below = $lab->fromBase([$white->x * LabSpace::EPSILON, $white->y * (LabSpace::EPSILON - 1e-12), $white->z * LabSpace::EPSILON]);
        $above = $lab->fromBase([$white->x * LabSpace::EPSILON, $white->y * (LabSpace::EPSILON + 1e-12), $white->z * LabSpace::EPSILON]);

        self::assertFloatClose(8.0, $below[0] ?? \NAN, Tolerance::absolute(Tolerances::PIECEWISE_JOIN));
        self::assertFloatClose(8.0, $above[0] ?? \NAN, Tolerance::absolute(Tolerances::PIECEWISE_JOIN));
    }

    public function testLabRoundTripAcrossBothBranches(): void
    {
        foreach ([[0.001, 0.002, 0.003], [0.9, 0.8, 0.7], [0.3, 0.004, 0.9], [-0.01, 0.05, 1.3]] as $xyz) {
            $back = ColorSpaces::lab()->toBase(\array_map(static fn(?float $v): float => $v ?? 0.0, ColorSpaces::lab()->fromBase($xyz)));

            foreach ($xyz as $index => $value) {
                self::assertFloatClose($value, $back[$index], Tolerances::linearAlgebra());
            }
        }
    }

    public function testOklabOfTheWhiteIsOneAndNeutral(): void
    {
        $oklab = ColorSpaces::oklab()->fromBase(WhitePoints::d65()->xyz->toList());

        self::assertFloatClose(1.0, $oklab[0] ?? \NAN, Tolerances::linearAlgebra());
        self::assertFloatClose(0.0, $oklab[1] ?? \NAN, Tolerances::linearAlgebra());
        self::assertFloatClose(0.0, $oklab[2] ?? \NAN, Tolerances::linearAlgebra());
    }

    public function testPolarFromOpponent(): void
    {
        $lch = ColorSpaces::lch()->fromBase([50.0, 3.0, 4.0]);

        self::assertSame(50.0, $lch[0]);
        self::assertFloatClose(5.0, $lch[1] ?? \NAN, Tolerances::linearAlgebra());
        self::assertFloatClose(\rad2deg(\atan2(4.0, 3.0)), $lch[2] ?? \NAN, Tolerances::linearAlgebra());

        $negative = ColorSpaces::lch()->fromBase([50.0, 0.0, -2.0]);
        self::assertFloatClose(270.0, $negative[2] ?? \NAN, Tolerances::linearAlgebra());
    }

    public function testPolarPowerlessHueAtAndBelowEpsilon(): void
    {
        $lch = ColorSpaces::lch();

        self::assertSame([50.0, 0.0, null], $lch->fromBase([50.0, 0.0015, 0.0]), 'C = ε is powerless (C ≤ ε).');
        self::assertSame([50.0, 0.0, null], $lch->fromBase([50.0, 0.0, 0.0]));
        self::assertNotNull($lch->fromBase([50.0, 0.0016, 0.0])[2]);

        self::assertSame([0.5, 0.0, null], ColorSpaces::oklch()->fromBase([0.5, 0.000004, 0.0]));
        self::assertNotNull(ColorSpaces::oklch()->fromBase([0.5, 0.000005, 0.0])[2]);
    }

    public function testPolarToOpponent(): void
    {
        $lab = ColorSpaces::oklch()->toBase([0.7, 0.1, 90.0]);

        self::assertSame(0.7, $lab[0]);
        self::assertFloatClose(0.0, $lab[1], Tolerances::linearAlgebra());
        self::assertFloatClose(0.1, $lab[2], Tolerances::linearAlgebra());
    }

    public function testPolarChannelsAndReferences(): void
    {
        self::assertSame(['l', 'c', 'h'], \array_map(static fn($c): string => $c->name, ColorSpaces::oklch()->channels()));
        self::assertSame(0.4, ColorSpaces::oklch()->channels()[1]->referenceMax);
        self::assertSame(150.0, ColorSpaces::lch()->channels()[1]->referenceMax);
        self::assertSame(1.0, ColorSpaces::oklch()->channels()[0]->referenceMax);
        self::assertTrue(ColorSpaces::oklch()->channels()[2]->isHue());
    }

    public function testPolarDefinitionValidation(): void
    {
        $this->expectException(InvalidColorSpaceDefinitionException::class);

        new PolarSpace('bad-polar', 'Bad', ColorSpaces::lab(), -1.0, 150.0);
    }

    public function testPolarChromaReferenceValidation(): void
    {
        $this->expectException(InvalidColorSpaceDefinitionException::class);

        new PolarSpace('bad-polar', 'Bad', ColorSpaces::lab(), 0.0, 0.0);
    }

    public function testPolarAllowsZeroEpsilon(): void
    {
        $space = new PolarSpace('lch-strict', 'LCh without epsilon', ColorSpaces::lab(), 0.0, 150.0);

        self::assertSame(0.0, $space->powerlessEpsilon);
        self::assertNotNull($space->fromBase([50.0, 1e-9, 0.0])[2]);
        self::assertNull($space->fromBase([50.0, 0.0, 0.0])[2]);
    }

}
