<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Space\Record;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Space\Exception\InvalidColorValueException;
use Domm98CZ\Color\Space\Record\CoordinateRecord;
use Domm98CZ\Color\Space\Record\DisplayP3;
use Domm98CZ\Color\Space\Record\Hsl;
use Domm98CZ\Color\Space\Record\Hsv;
use Domm98CZ\Color\Space\Record\Hwb;
use Domm98CZ\Color\Space\Record\Lab;
use Domm98CZ\Color\Space\Record\Lch;
use Domm98CZ\Color\Space\Record\Oklab;
use Domm98CZ\Color\Space\Record\Oklch;
use Domm98CZ\Color\Space\Record\Rgb;
use Domm98CZ\Color\Space\Record\Rgb255;
use Domm98CZ\Color\Space\Record\Xyz;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CoordinateRecordsTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<CoordinateRecord>, string}>
     */
    public static function records(): iterable
    {
        yield 'Rgb' => [Rgb::class, 'srgb'];
        yield 'Rgb255' => [Rgb255::class, 'srgb'];
        yield 'DisplayP3' => [DisplayP3::class, 'display-p3'];
        yield 'Hsl' => [Hsl::class, 'hsl'];
        yield 'Hwb' => [Hwb::class, 'hwb'];
        yield 'Hsv' => [Hsv::class, 'hsv'];
        yield 'Xyz' => [Xyz::class, 'xyz-d65'];
        yield 'Lab' => [Lab::class, 'lab'];
        yield 'Lch' => [Lch::class, 'lch'];
        yield 'Oklab' => [Oklab::class, 'oklab'];
        yield 'Oklch' => [Oklch::class, 'oklch'];
    }

    /**
     * @param class-string<CoordinateRecord> $record
     */
    #[DataProvider('records')]
    public function testRecordRoundTripsThroughColor(string $record, string $spaceId): void
    {
        $color = Color::fromRgba(51, 102, 204, 0.4);
        $view = $color->in($record);

        self::assertSame($spaceId, $record::space()->id());
        self::assertSame(0.4, $view->alpha());
        self::assertTrue(Color::from($view)->isCloseTo($color, Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB)));
        self::assertSame($view->coordinates(), $record::fromCoordinates($view->coordinates(), 0.4)->coordinates());
    }

    public function testRgb255Scale(): void
    {
        $rgb = new Rgb255(255.0, 127.5, 0.0);

        self::assertSame([1.0, 0.5, 0.0], $rgb->coordinates());
        self::assertSame(255.0, Rgb255::fromCoordinates([1.0, 0.5, 0.0], 1.0)->r);
    }

    public function testMissingComponentsReadAsZeroExceptHue(): void
    {
        $oklch = Oklch::fromCoordinates([null, 0.1, null], 1.0);

        self::assertSame(0.0, $oklch->l);
        self::assertNull($oklch->h);
    }

    public function testHueIsNormalised(): void
    {
        self::assertSame(330.0, (new Oklch(0.5, 0.1, -30.0))->h);
        self::assertSame(10.0, (new Hsl(370.0, 50.0, 50.0))->h);
    }

    public function testWithMethodsAreImmutable(): void
    {
        $lch = new Lch(50.0, 30.0, 120.0, 0.5);

        self::assertSame([60.0, 30.0, 120.0], $lch->withL(60.0)->coordinates());
        self::assertSame([50.0, 10.0, 120.0], $lch->withC(10.0)->coordinates());
        self::assertSame([50.0, 30.0, null], $lch->withH(null)->coordinates());
        self::assertSame(0.9, $lch->withAlpha(0.9)->alpha);
        self::assertSame([50.0, 30.0, 120.0], $lch->coordinates());
        self::assertSame(0.5, $lch->alpha());

        $hwb = new Hwb(10.0, 20.0, 30.0);
        self::assertSame([10.0, 25.0, 30.0], $hwb->withW(25.0)->coordinates());
        self::assertSame([10.0, 20.0, 35.0], $hwb->withB(35.0)->coordinates());
        self::assertSame([20.0, 20.0, 30.0], $hwb->withH(20.0)->coordinates());
    }

    public function testRecordsValidateValues(): void
    {
        $this->expectException(InvalidColorValueException::class);

        new Lab(\NAN, 0.0, 0.0);
    }

    public function testRecordsValidateAlpha(): void
    {
        $this->expectException(InvalidColorValueException::class);

        new Rgb(0.1, 0.2, 0.3, 1.1);
    }

    public function testRecordsValidateArity(): void
    {
        $this->expectException(InvalidColorValueException::class);

        Oklab::fromCoordinates([0.5, 0.1], 1.0);
    }

    /**
     * @return iterable<string, array{CoordinateRecord}>
     */
    public static function defaultInstances(): iterable
    {
        yield 'Rgb' => [new Rgb(0.1, 0.2, 0.3)];
        yield 'Rgb255' => [new Rgb255(10.0, 20.0, 30.0)];
        yield 'DisplayP3' => [new DisplayP3(0.1, 0.2, 0.3)];
        yield 'Hsl' => [new Hsl(10.0, 20.0, 30.0)];
        yield 'Hwb' => [new Hwb(10.0, 20.0, 30.0)];
        yield 'Hsv' => [new Hsv(10.0, 20.0, 30.0)];
        yield 'Xyz' => [new Xyz(0.1, 0.2, 0.3)];
        yield 'Lab' => [new Lab(10.0, 20.0, 30.0)];
        yield 'Lch' => [new Lch(10.0, 20.0, 30.0)];
        yield 'Oklab' => [new Oklab(0.1, 0.2, 0.3)];
        yield 'Oklch' => [new Oklch(0.1, 0.2, 30.0)];
    }

    #[DataProvider('defaultInstances')]
    public function testRecordsDefaultToOpaque(CoordinateRecord $record): void
    {
        self::assertSame(1.0, $record->alpha());
    }

    #[DataProvider('records')]
    public function testMissingNonHueComponentsReadAsZero(string $record, string $spaceId): void
    {
        /** @var class-string<CoordinateRecord> $record */
        $view = $record::fromCoordinates([null, null, null], 0.5);
        $space = $record::space();

        foreach ($space->channels() as $index => $channel) {
            if ($channel->isHue()) {
                self::assertNull($view->coordinates()[$index], $spaceId);
            } else {
                self::assertSame(0.0, $view->coordinates()[$index], $spaceId);
            }
        }
    }

}
