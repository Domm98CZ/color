<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Operation;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Operation\Exception\InvalidOperationException;
use Domm98CZ\Color\Operation\TintShadeTone;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\TestCase;

final class TintShadeToneTest extends TestCase
{
    use FloatAssertions;

    public function testTintAndShadeInSrgb(): void
    {
        $tst = new TintShadeTone(ColorSpaces::srgb());
        $color = Color::fromSrgb(0.2, 0.4, 0.6);

        self::assertSame([0.4, 0.55, 0.7], \array_map(static fn(?float $v): float => \round($v ?? \NAN, 12), $tst->tint($color, 0.25)->coordinates()));
        self::assertSame([0.15, 0.3, 0.45], \array_map(static fn(?float $v): float => \round($v ?? \NAN, 12), $tst->shade($color, 0.25)->coordinates()));
        self::assertSame([1.0, 1.0, 1.0], $tst->tint($color, 1.0)->coordinates());
        self::assertSame([0.2, 0.4, 0.6], $tst->shade($color, 0.0)->coordinates());
    }

    public function testDefaultToneKeepsTheOkLightness(): void
    {
        $color = Color::fromHex('#3366cc');
        $tone = (new TintShadeTone(ColorSpaces::oklch()))->tone($color, 0.4);
        $okL = $color->to(ColorSpaces::oklab())->coordinates()[0] ?? \NAN;

        self::assertFloatClose($okL, $tone->coordinate('l') ?? \NAN, Tolerances::linearAlgebra());
        self::assertFloatClose(($color->to(ColorSpaces::oklch())->coordinate('c') ?? \NAN) * 0.6, $tone->coordinate('c') ?? \NAN, Tolerances::linearAlgebra());
        self::assertFloatClose($color->to(ColorSpaces::oklch())->coordinate('h') ?? \NAN, $tone->coordinate('h') ?? \NAN, Tolerances::linearAlgebra(), 'the hue of the gray is carried from the color');
    }

    public function testToneWithAnExplicitGray(): void
    {
        $tone = (new TintShadeTone(ColorSpaces::srgb()))->tone(Color::fromSrgb(1.0, 0.0, 0.0), 0.5, Color::fromHex('#808080'));

        self::assertFloatClose((1.0 + 128 / 255) / 2, $tone->coordinates()[0] ?? \NAN, Tolerances::linearAlgebra());
        self::assertFloatClose(64 / 255, $tone->coordinates()[1] ?? \NAN, Tolerances::linearAlgebra());
    }

    public function testTheOpacityIsKept(): void
    {
        $tst = new TintShadeTone(ColorSpaces::oklab());
        $color = Color::fromSrgb(0.2, 0.4, 0.6, 0.3);

        self::assertSame(0.3, $tst->tint($color, 0.5)->alpha());
        self::assertSame(0.3, $tst->shade($color, 0.5)->alpha());
        self::assertSame(0.3, $tst->tone($color, 0.5)->alpha());
        self::assertTrue($tst->tint(Color::of(ColorSpaces::srgb(), [0.2, 0.4, 0.6], null), 0.5)->isAlphaMissing());
    }

    public function testRejectsAmountsOutsideTheUnitInterval(): void
    {
        $tst = new TintShadeTone(ColorSpaces::oklab());
        $color = Color::fromHex('#3366cc');

        foreach (['tint' => static fn() => $tst->tint($color, 1.5), 'shade' => static fn() => $tst->shade($color, -0.1), 'tone' => static fn() => $tst->tone($color, \NAN)] as $name => $call) {
            try {
                $call();
                self::fail($name . ' must reject the amount');
            } catch (InvalidOperationException $exception) {
                self::assertStringContainsString(\sprintf('The %s amount must be in [0, 1]', $name), $exception->getMessage());
            }
        }
    }
}
