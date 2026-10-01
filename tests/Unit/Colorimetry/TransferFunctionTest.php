<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Colorimetry;

use Domm98CZ\Color\Colorimetry\Algorithm\GammaTransferFunction;
use Domm98CZ\Color\Colorimetry\Algorithm\ProPhotoTransferFunction;
use Domm98CZ\Color\Colorimetry\Algorithm\SrgbTransferFunction;
use Domm98CZ\Color\Colorimetry\Contract\TransferFunction;
use Domm98CZ\Color\Colorimetry\Exception\InvalidColorimetricDataException;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TransferFunctionTest extends TestCase
{
    use FloatAssertions;

    /**
     * @return iterable<string, array{TransferFunction}>
     */
    public static function functions(): iterable
    {
        yield 'sRGB' => [new SrgbTransferFunction()];
        yield 'a98 gamma 563/256' => [new GammaTransferFunction(563 / 256)];
        yield 'rec2020 gamma 2.4' => [new GammaTransferFunction(2.4)];
        yield 'ProPhoto' => [new ProPhotoTransferFunction()];
    }

    #[DataProvider('functions')]
    public function testEndpointsAreFixed(TransferFunction $function): void
    {
        self::assertSame(0.0, $function->toLinear(0.0));
        self::assertSame(0.0, $function->toEncoded(0.0));
        self::assertFloatClose(1.0, $function->toLinear(1.0), Tolerance::absolute(\PHP_FLOAT_EPSILON));
        self::assertFloatClose(1.0, $function->toEncoded(1.0), Tolerance::absolute(\PHP_FLOAT_EPSILON));
    }

    #[DataProvider('functions')]
    public function testNegativeValuesAreMirrored(TransferFunction $function): void
    {
        foreach ([0.001, 0.02, 0.3, 0.9, 1.4] as $value) {
            self::assertSame(-$function->toLinear($value), $function->toLinear(-$value));
            self::assertSame(-$function->toEncoded($value), $function->toEncoded(-$value));
        }
    }

    #[DataProvider('functions')]
    public function testRoundTripOverExtendedRange(TransferFunction $function): void
    {
        for ($i = -150; $i <= 150; $i++) {
            $encoded = $i / 100;

            self::assertFloatClose($encoded, $function->toEncoded($function->toLinear($encoded)), Tolerances::linearAlgebra(), \sprintf('%s at %s', $function->id(), $encoded));
        }
    }

    #[DataProvider('functions')]
    public function testMonotonic(TransferFunction $function): void
    {
        $previous = $function->toLinear(-1.5);

        for ($i = -1499; $i <= 1500; $i++) {
            $current = $function->toLinear($i / 1000);
            self::assertGreaterThanOrEqual($previous, $current, $function->id());
            $previous = $current;
        }
    }

    public function testSrgbSegmentsAtTheBreakpoint(): void
    {
        $srgb = new SrgbTransferFunction();

        // IEC 61966-2-1: linear segment up to 0.04045 (encoded) / 0.0031308 (linear).
        self::assertSame(0.04045 / 12.92, $srgb->toLinear(0.04045));
        self::assertSame(12.92 * 0.0031308, $srgb->toEncoded(0.0031308));
        // The two segments meet within 1e-8 (the published constants are rounded).
        self::assertFloatClose($srgb->toLinear(0.04045), ((0.04045 + 0.055) / 1.055) ** 2.4, Tolerance::absolute(Tolerances::PIECEWISE_JOIN));
        self::assertFloatClose(0.2140, $srgb->toLinear(0.5), Tolerance::absolute(Tolerances::printedDecimals(4)));
    }

    public function testProPhotoSegments(): void
    {
        $prophoto = new ProPhotoTransferFunction();

        self::assertSame((16 / 512) / 16, $prophoto->toLinear(16 / 512));
        self::assertSame((16 / 512 + 1e-9) ** 1.8, $prophoto->toLinear(16 / 512 + 1e-9));
        self::assertSame(16 * (1 / 512 - 1e-12), $prophoto->toEncoded(1 / 512 - 1e-12));
        self::assertSame((1 / 512) ** (1 / 1.8), $prophoto->toEncoded(1 / 512));
    }

    public function testGammaIdentifierAndValidation(): void
    {
        self::assertSame('gamma-2.19921875', (new GammaTransferFunction(563 / 256))->id());
        self::assertSame('gamma-2.4', (new GammaTransferFunction(2.4))->id());
        self::assertSame('srgb', (new SrgbTransferFunction())->id());
        self::assertSame('prophoto', (new ProPhotoTransferFunction())->id());

        $this->expectException(InvalidColorimetricDataException::class);
        new GammaTransferFunction(0.0);
    }

    public function testGammaIdentifierOfAnIntegerGamma(): void
    {
        self::assertSame('gamma-2', (new \Domm98CZ\Color\Colorimetry\Algorithm\GammaTransferFunction(2.0))->id());
    }

}
