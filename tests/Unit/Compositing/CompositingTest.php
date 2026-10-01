<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Compositing;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Compositing\Algorithm\SourceOver;
use Domm98CZ\Color\Compositing\Exception\InvalidCompositingSpaceException;
use Domm98CZ\Color\Compositing\LayerStack;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\TestCase;

final class CompositingTest extends TestCase
{
    use FloatAssertions;

    public function testSourceOverAnOpaqueBackdrop(): void
    {
        $result = (new SourceOver())->composite(Color::fromRgba(255, 255, 255, 0.5), Color::fromRgb(0, 0, 0));

        self::assertSame('srgb', $result->space()->id());
        self::assertSame([0.5, 0.5, 0.5], $result->coordinates());
        self::assertSame(1.0, $result->alpha());
    }

    public function testAnOpaqueLayerGivesExactlyOpaqueResult(): void
    {
        // αs + 1·(1 − αs) is exactly 1 in binary64, so an opaque backdrop stays exactly opaque.
        self::assertSame(1.0, (new SourceOver())->composite(Color::fromRgba(0, 0, 0, 0.1), Color::fromRgb(255, 255, 255))->alpha());
        self::assertSame(1.0, (new SourceOver())->composite(Color::fromRgb(0, 0, 0), Color::fromRgba(255, 255, 255, 0.3))->alpha());
    }

    public function testTwoTranslucentLayers(): void
    {
        $result = (new SourceOver())->composite(Color::fromRgba(255, 255, 255, 0.5), Color::fromRgba(0, 0, 0, 0.5));

        self::assertFloatClose(0.75, $result->alpha(), Tolerances::linearAlgebra());
        foreach ($result->coordinates() as $value) {
            self::assertNotNull($value);
            self::assertFloatClose(0.5 / 0.75, $value, Tolerances::linearAlgebra());
        }
    }

    public function testTransparentOverTransparentIsTransparentBlack(): void
    {
        $result = (new SourceOver())->composite(Color::fromRgba(255, 0, 0, 0.0), Color::fromRgba(0, 255, 0, 0.0));

        self::assertSame([0.0, 0.0, 0.0], $result->coordinates());
        self::assertSame(0.0, $result->alpha());
    }

    public function testOpaqueSourceHidesTheBackdrop(): void
    {
        $source = Color::fromRgb(10, 20, 30);

        self::assertTrue((new SourceOver())->composite($source, Color::fromRgba(200, 0, 0, 0.5))->isCloseTo($source, Tolerances::linearAlgebra()));
    }

    public function testMissingValuesCountAsZero(): void
    {
        $white = Color::fromRgb(255, 255, 255);

        self::assertTrue((new SourceOver())->composite(Color::fromRgb(0, 0, 0)->withAlpha(null), $white)->equals($white), 'missing alpha = 0');
        self::assertSame([0.5, 0.5, 0.5], (new SourceOver())->composite(Color::of(ColorSpaces::srgb(), [null, 0.0, 0.0], 0.5), $white)->coordinates());
        self::assertSame([0.5, 0.0, 1.0], (new SourceOver())->composite(Color::of(ColorSpaces::srgb(), [0.0, 0.0, 1.0], 0.5), Color::of(ColorSpaces::srgb(), [1.0, null, 1.0]))->coordinates());
    }

    public function testCompositingSpace(): void
    {
        $linear = new SourceOver(ColorSpaces::srgbLinear());
        $result = $linear->composite(Color::fromRgba(255, 255, 255, 0.5), Color::fromRgb(0, 0, 0));

        self::assertSame('srgb-linear', $result->space()->id());
        self::assertSame([0.5, 0.5, 0.5], $result->coordinates());
        self::assertSame('source-over[space=srgb-linear]@r1', $linear->id()->identifier());
        self::assertSame('source-over[space=srgb]@r1', (new SourceOver())->id()->identifier());
        self::assertSame('Compositing and Blending Level 1', (new SourceOver())->id()->sources[0]->title);
    }

    public function testCylindricalSpacesAreRejected(): void
    {
        $this->expectException(InvalidCompositingSpaceException::class);
        $this->expectExceptionMessage('"oklch" has a hue channel');

        new SourceOver(ColorSpaces::oklch());
    }

    public function testLayerStack(): void
    {
        $top = Color::fromRgba(255, 255, 255, 0.5);
        $middle = Color::fromRgba(0, 0, 255, 0.5);
        $bottom = Color::fromHex('#000000');
        $stack = LayerStack::of($top, $middle)->over($bottom);
        $result = $stack->composite();

        self::assertSame([$top, $middle, $bottom], $stack->layers);
        self::assertSame([$top, $middle, $bottom], $result->layers);
        self::assertSame([0.5, 0.5, 0.75], $result->color->coordinates());
        self::assertTrue($result->isOpaque());
        self::assertSame('source-over[space=srgb]@r1', $result->operator->identifier());
        self::assertSame([$top], LayerStack::of($top)->layers);
        self::assertSame([$top, $bottom], LayerStack::of($top, ...['backdrop' => $bottom])->layers, 'named variadic arguments still give a list');
    }

    public function testLayerStackWithoutAnOpaqueBottom(): void
    {
        $result = LayerStack::of(Color::fromRgba(255, 255, 255, 0.5))->composite();

        self::assertFalse($result->isOpaque());
        self::assertSame(0.5, $result->color->alpha());

        $inHsl = LayerStack::of(Color::fromHsl(0, 100, 50))->composite();
        self::assertSame('srgb', $inHsl->color->space()->id(), 'a single layer is converted to the operator space');
    }

    public function testLayerStackWithAnotherOperator(): void
    {
        $result = LayerStack::of(Color::fromRgba(255, 255, 255, 0.5))->over(Color::fromRgb(0, 0, 0))->composite(new SourceOver(ColorSpaces::srgbLinear()));

        self::assertSame('srgb-linear', $result->color->space()->id());
    }
}
