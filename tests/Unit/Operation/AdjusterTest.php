<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Operation;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Operation\Adjuster;
use Domm98CZ\Color\Operation\ChromaModel;
use Domm98CZ\Color\Operation\Exception\InvalidOperationException;
use Domm98CZ\Color\Operation\HueModel;
use Domm98CZ\Color\Operation\LightnessModel;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdjusterTest extends TestCase
{
    use FloatAssertions;

    public function testLightnessInEachModel(): void
    {
        $color = Color::fromHex('#3366cc');
        $adjust = new Adjuster();

        $ok = $adjust->shiftLightness($color, 0.1, LightnessModel::OklchL);
        $lab = $adjust->shiftLightness($color, 10.0, LightnessModel::CieLabL);
        $hsl = $adjust->shiftLightness($color, 10.0, LightnessModel::HslL);

        self::assertSame('srgb', $ok->space()->id(), 'the result is in the space of the input');
        self::assertFloatClose(($color->to(ColorSpaces::oklch())->coordinates()[0] ?? \NAN) + 0.1, $ok->to(ColorSpaces::oklch())->coordinates()[0] ?? \NAN, Tolerances::linearAlgebra());
        self::assertFloatClose(($color->to(ColorSpaces::lab())->coordinates()[0] ?? \NAN) + 10.0, $lab->to(ColorSpaces::lab())->coordinates()[0] ?? \NAN, Tolerances::linearAlgebra());
        self::assertSame('#5c85d6', $hsl->toHex(), 'hsl(220 60% 50%) → hsl(220 60% 60%)');
    }

    public function testWithLightness(): void
    {
        $result = (new Adjuster())->withLightness(Color::fromHex('#3366cc'), 0.8, LightnessModel::OklchL);

        self::assertFloatClose(0.8, $result->to(ColorSpaces::oklch())->coordinates()[0] ?? \NAN, Tolerances::linearAlgebra());
    }

    public function testChroma(): void
    {
        $color = Color::fromOklch(0.6, 0.2, 250.0);
        $adjust = new Adjuster();

        self::assertFloatClose(0.1, $adjust->scaleChroma($color, 0.5, ChromaModel::Oklch)->coordinate('c') ?? \NAN, Tolerances::linearAlgebra());
        self::assertSame(0.0, $adjust->scaleChroma($color, 0.0, ChromaModel::Oklch)->coordinate('c'));

        $lch = $color->to(ColorSpaces::lch())->coordinates()[1] ?? \NAN;
        self::assertFloatClose($lch * 1.5, $adjust->scaleChroma($color, 1.5, ChromaModel::CieLch)->to(ColorSpaces::lch())->coordinates()[1] ?? \NAN, Tolerances::linearAlgebra());
    }

    public function testRotateHue(): void
    {
        $adjust = new Adjuster();

        self::assertSame('#cc9933', $adjust->rotateHue(Color::fromHex('#3366cc'), 180.0, HueModel::Hsl)->toHex());
        self::assertFloatClose(10.0, $adjust->rotateHue(Color::fromOklch(0.6, 0.1, 250.0), 120.0, HueModel::Oklch)->coordinate('h') ?? \NAN, Tolerances::linearAlgebra());
        self::assertFloatClose(330.0, $adjust->rotateHue(Color::fromLch(60.0, 30.0, 30.0), -60.0, HueModel::CieLch)->coordinate('h') ?? \NAN, Tolerances::linearAlgebra());
    }

    public function testAMissingHueStaysMissing(): void
    {
        $gray = Color::of(ColorSpaces::oklch(), [0.5, 0.0, null]);

        self::assertNull((new Adjuster())->rotateHue($gray, 90.0, HueModel::Oklch)->coordinate('h'));
    }

    public function testMissingValuesCountAsZeroInArithmetic(): void
    {
        $adjust = new Adjuster();

        self::assertSame(0.1, $adjust->shiftLightness(Color::of(ColorSpaces::oklch(), [null, 0.1, 30.0]), 0.1, LightnessModel::OklchL)->coordinate('l'));
        self::assertSame(0.0, $adjust->scaleChroma(Color::of(ColorSpaces::oklch(), [0.5, null, 30.0]), 2.0, ChromaModel::Oklch)->coordinate('c'));
    }

    public function testKeepsTheAlpha(): void
    {
        $adjust = new Adjuster();

        self::assertSame(0.4, $adjust->shiftLightness(Color::fromSrgb(0.2, 0.4, 0.6, 0.4), 0.05, LightnessModel::OklchL)->alpha());
        self::assertTrue($adjust->shiftLightness(Color::of(ColorSpaces::srgb(), [0.2, 0.4, 0.6], null), 0.05, LightnessModel::OklchL)->isAlphaMissing());
    }

    public function testNothingIsClipped(): void
    {
        $result = (new Adjuster())->scaleChroma(Color::fromHex('#3366cc'), 3.0, ChromaModel::Oklch);

        self::assertFalse($result->isInGamut());
    }

    public function testModelSpaces(): void
    {
        self::assertSame(['oklch', 'lch', 'hsl'], \array_map(static fn(LightnessModel $m): string => $m->space()->id(), LightnessModel::cases()));
        self::assertSame([0, 0, 2], \array_map(static fn(LightnessModel $m): int => $m->channel(), LightnessModel::cases()));
        self::assertSame(['oklch', 'lch'], \array_map(static fn(ChromaModel $m): string => $m->space()->id(), ChromaModel::cases()));
        self::assertSame(['oklch', 'lch', 'hsl'], \array_map(static fn(HueModel $m): string => $m->space()->id(), HueModel::cases()));
        self::assertSame([2, 2, 0], \array_map(static fn(HueModel $m): int => $m->channel(), HueModel::cases()));
    }

    /**
     * @return iterable<string, array{\Closure(Adjuster, Color): Color, string}>
     */
    public static function invalid(): iterable
    {
        yield 'lightness NaN' => [static fn(Adjuster $a, Color $c): Color => $a->shiftLightness($c, \NAN, LightnessModel::OklchL), 'lightness change must be a finite number'];
        yield 'withLightness INF' => [static fn(Adjuster $a, Color $c): Color => $a->withLightness($c, \INF, LightnessModel::OklchL), 'lightness must be a finite number'];
        yield 'negative chroma scale' => [static fn(Adjuster $a, Color $c): Color => $a->scaleChroma($c, -0.5, ChromaModel::Oklch), 'chroma scale must be ≥ 0'];
        yield 'chroma scale NaN' => [static fn(Adjuster $a, Color $c): Color => $a->scaleChroma($c, \NAN, ChromaModel::Oklch), 'chroma scale must be a finite number'];
        yield 'rotation INF' => [static fn(Adjuster $a, Color $c): Color => $a->rotateHue($c, -\INF, HueModel::Oklch), 'hue rotation must be a finite number'];
    }

    /**
     * @param \Closure(Adjuster, Color): Color $call
     */
    #[DataProvider('invalid')]
    public function testRejectsInvalidArguments(\Closure $call, string $message): void
    {
        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage($message);
        $call(new Adjuster(), Color::fromHex('#3366cc'));
    }
}
