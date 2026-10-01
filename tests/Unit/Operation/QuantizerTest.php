<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Operation;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Notation\Exception\OutOfGamutException;
use Domm98CZ\Color\Operation\Exception\InvalidOperationException;
use Domm98CZ\Color\Operation\Exception\UnsupportedOperationSpaceException;
use Domm98CZ\Color\Operation\Quantizer;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Fake\PercentSrgbSpace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuantizerTest extends TestCase
{
    public function testEightBitsMatchesHex(): void
    {
        $color = Color::fromSrgb(0.2, 0.4, 0.6, 0.5);
        $quantized = (new Quantizer())->quantize($color);

        self::assertSame([51, 102, 153], $quantized->codes);
        self::assertSame(128, $quantized->alphaCode, '0.5 · 255 = 127.5 rounds half up');
        self::assertSame(8, $quantized->bits);
        self::assertSame(255, $quantized->maxCode());
        self::assertSame(\strtolower('#336699' . \sprintf('%02x', $quantized->alphaCode)), $color->toHex());
        self::assertSame([51 / 255, 102 / 255, 153 / 255], $quantized->color->coordinates());
        self::assertSame(128 / 255, $quantized->color->alpha());
    }

    public function testOtherDepthsAndSpaces(): void
    {
        $quantized = (new Quantizer(10, ColorSpaces::displayP3()))->quantize(Color::fromSrgb(1.0, 0.0, 0.0));

        self::assertSame('display-p3', $quantized->color->space()->id());
        self::assertSame(1023, $quantized->maxCode());
        self::assertSame(1023, $quantized->alphaCode);
        self::assertSame([939, 205, 142], $quantized->codes, 'CSS Color 4: sRGB red is color(display-p3 0.91748756 0.20028681 0.13856059)');

        self::assertSame([1, 0, 1], (new Quantizer(1))->quantize(Color::fromSrgb(0.5, 0.49, 1.0))->codes);
        self::assertSame([65535, 0, 32768], (new Quantizer(16))->quantize(Color::fromSrgb(1.0, 0.0, 0.5))->codes);
    }

    public function testToleranceAndMissingValues(): void
    {
        $quantized = (new Quantizer())->quantize(Color::of(ColorSpaces::srgb(), [1.0000005, -0.0000005, null], null));

        self::assertSame([255, 0, 0], $quantized->codes);
        self::assertSame(0, $quantized->alphaCode);
    }

    public function testTheGamutToleranceIsInclusive(): void
    {
        $quantized = (new Quantizer())->quantize(Color::fromSrgb(1.0 + Color::DEFAULT_GAMUT_TOLERANCE, -Color::DEFAULT_GAMUT_TOLERANCE, 0.5));

        self::assertSame([255, 0, 128], $quantized->codes);
    }

    public function testOutOfGamutIsNotClipped(): void
    {
        $this->expectException(OutOfGamutException::class);
        $this->expectExceptionMessage('outside the srgb gamut');
        (new Quantizer())->quantize(Color::fromSrgb(1.01, 0.0, 0.0));
    }

    public function testBelowTheGamut(): void
    {
        $this->expectException(OutOfGamutException::class);
        (new Quantizer())->quantize(Color::fromSrgb(0.5, -0.01, 0.0));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidDepths(): iterable
    {
        yield 'zero' => [0];
        yield 'seventeen' => [17];
    }

    #[DataProvider('invalidDepths')]
    public function testRejectsDepths(int $bits): void
    {
        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('between 1 and 16');
        new Quantizer($bits);
    }

    public function testRejectsSpacesWithoutAnRgbCube(): void
    {
        foreach ([ColorSpaces::hsl(), ColorSpaces::oklab(), new PercentSrgbSpace()] as $space) {
            try {
                new Quantizer(8, $space);
                self::fail($space->id());
            } catch (UnsupportedOperationSpaceException $exception) {
                self::assertStringContainsString(\sprintf('"%s" is not one', $space->id()), $exception->getMessage());
            }
        }

        self::assertSame('test-percent-srgb', (new Quantizer(8, new PercentSrgbSpace(true)))->space->id());
    }
}
