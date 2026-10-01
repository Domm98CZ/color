<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Notation;

use Domm98CZ\Color\Notation\Exception\ColorParseException;
use Domm98CZ\Color\Notation\Exception\InvalidColorJsonException;
use Domm98CZ\Color\Notation\Exception\OutOfGamutException;
use Domm98CZ\Color\Notation\HexNotation;
use Domm98CZ\Color\Notation\JsonNotation;
use Domm98CZ\Color\Space\ColorSpaceRegistry;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\ColorValue;
use Domm98CZ\Color\Space\Exception\InvalidColorValueException;
use Domm98CZ\Color\Space\Exception\UnknownColorSpaceException;
use Domm98CZ\Color\Tests\Fake\PercentSrgbSpace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HexAndJsonNotationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<int>, int}>
     */
    public static function hexInputs(): iterable
    {
        yield '#rgb' => ['#f80', [255, 136, 0], 255];
        yield 'rgb without #' => ['F80', [255, 136, 0], 255];
        yield '#rgba' => ['#f808', [255, 136, 0], 136];
        yield '#rrggbb' => ['#3366CC', [51, 102, 204], 255];
        yield 'rrggbbaa' => ['3366cc80', [51, 102, 204], 128];
    }

    /**
     * @param list<int> $rgb
     */
    #[DataProvider('hexInputs')]
    public function testHexParsing(string $hex, array $rgb, int $alpha): void
    {
        $value = (new HexNotation())->parse($hex);

        self::assertSame('srgb', $value->space->id());
        self::assertSame(\array_map(static fn(int $v): float => $v / 255.0, $rgb), $value->coordinates);
        self::assertSame($alpha / 255.0, $value->alpha);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function invalidHex(): iterable
    {
        yield 'empty' => ['', 0];
        yield 'hash only' => ['#', 1];
        yield 'five digits' => ['#12345', 1];
        yield 'non-hex' => ['#ggg', 1];
        yield 'whitespace' => [' #fff', 0];
        yield 'double hash' => ['##fff', 1];
    }

    #[DataProvider('invalidHex')]
    public function testInvalidHex(string $hex, int $offset): void
    {
        try {
            (new HexNotation())->parse($hex);
            self::fail('Must not parse.');
        } catch (ColorParseException $exception) {
            self::assertSame($offset, $exception->getOffset());
            self::assertSame('3, 4, 6 or 8 hexadecimal digits', $exception->getExpected());
        }
    }

    public function testHexFormatting(): void
    {
        $hex = new HexNotation();
        $srgb = ColorSpaces::srgb();

        self::assertSame('#3366cc', $hex->format(new ColorValue($srgb, [0.2, 0.4, 0.8])));
        self::assertSame('#3366ccff', $hex->format(new ColorValue($srgb, [0.2, 0.4, 0.8]), true));
        self::assertSame('#3366cc80', $hex->format(new ColorValue($srgb, [0.2, 0.4, 0.8], 0.5)));
        self::assertSame('#3366cc', $hex->format(new ColorValue($srgb, [0.2, 0.4, 0.8], 0.999)), 'alpha rounds to ff');
        self::assertSame('#00800000', $hex->format(new ColorValue($srgb, [null, 128 / 255, 0.0], null)), 'missing values are 0');
        self::assertSame('#ff0000', $hex->format(new ColorValue(ColorSpaces::hsl(), [0.0, 100.0, 50.0])), 'converted to sRGB');
        self::assertSame('#ffffff', $hex->format(new ColorValue($srgb, [1.0000009, 1.0, 1.0])), 'within the tolerance');
        self::assertSame('#000000', $hex->format(new ColorValue($srgb, [-0.0000009, 0.0, 0.0])), 'within the tolerance');
        self::assertSame('#808080', $hex->format(new ColorValue($srgb, [127.5 / 255, 127.5 / 255, 127.5 / 255])), 'half rounds up');
        self::assertSame('#ff0000', $hex->format(new ColorValue($srgb, [1.0 + HexNotation::GAMUT_TOLERANCE, -HexNotation::GAMUT_TOLERANCE, 0.0])), 'the tolerance is inclusive');
    }

    /**
     * @return iterable<string, array{list<float>}>
     */
    public static function outOfGamut(): iterable
    {
        yield 'above' => [[1.0000011, 0.0, 0.0]];
        yield 'below' => [[0.0, 0.0, -0.0000011]];
    }

    /**
     * @param list<float> $coordinates
     */
    #[DataProvider('outOfGamut')]
    public function testHexRefusesOutOfGamutColors(array $coordinates): void
    {
        $value = new ColorValue(ColorSpaces::srgb(), $coordinates);

        try {
            (new HexNotation())->format($value);
            self::fail('Must not clip.');
        } catch (OutOfGamutException $exception) {
            self::assertSame($value->space, $exception->getSpace());
            self::assertSame($coordinates, $exception->getCoordinates());
            self::assertSame('srgb', $exception->getTargetSpace()->id());
            self::assertStringContainsString('outside the sRGB gamut', $exception->getMessage());
        }
    }

    public function testOutOfGamutMessageShowsTheColorInCss(): void
    {
        $this->expectException(OutOfGamutException::class);
        $this->expectExceptionMessage('The color color(display-p3 1 0 0) is outside the sRGB gamut');

        (new HexNotation())->format(new ColorValue(ColorSpaces::displayP3(), [1.0, 0.0, 0.0]));
    }

    public function testJsonRoundTrip(): void
    {
        $json = new JsonNotation();
        $value = new ColorValue(ColorSpaces::oklch(), [0.7, 0.12, null], null);
        $encoded = $json->encode($value);

        self::assertSame('{"v":1,"space":"oklch","coords":[0.7,0.12,null],"alpha":null}', $encoded);
        $decoded = $json->decode($encoded);
        self::assertSame(ColorSpaces::oklch(), $decoded->space);
        self::assertSame([0.7, 0.12, null], $decoded->coordinates);
        self::assertNull($decoded->alpha);

        $integers = $json->decode('{"v":1,"space":"srgb","coords":[1,0,0.5],"alpha":1}');
        self::assertSame([1.0, 0.0, 0.5], $integers->coordinates);
        self::assertSame(1.0, $integers->alpha);
    }

    public function testJsonUsesTheGivenRegistry(): void
    {
        $document = '{"v":1,"space":"test-percent-srgb","coords":[10,20,30],"alpha":0.5}';

        self::assertSame('test-percent-srgb', (new JsonNotation(ColorSpaceRegistry::default()->with(new PercentSrgbSpace())))->decode($document)->space->id());

        $this->expectException(UnknownColorSpaceException::class);
        (new JsonNotation())->decode($document);
    }

    /**
     * @return iterable<string, array{string, class-string<\Throwable>, string}>
     */
    public static function invalidJson(): iterable
    {
        yield 'syntax' => ['{"v":1,', InvalidColorJsonException::class, 'Invalid JSON'];
        yield 'too deep' => ['{"v":1,"space":"srgb","coords":[[1],0,0],"alpha":1}', InvalidColorJsonException::class, 'Invalid JSON'];
        yield 'not an object' => ['[1, 2, 3]', InvalidColorJsonException::class, 'must be a JSON object'];
        yield 'scalar' => ['"red"', InvalidColorJsonException::class, 'must be a JSON object'];
        yield 'unknown key' => ['{"v":1,"space":"srgb","coords":[0,0,0],"alpha":1,"name":"x"}', InvalidColorJsonException::class, 'Unknown key(s) in a version 1 color: name'];
        yield 'missing version' => ['{"space":"srgb","coords":[0,0,0],"alpha":1}', InvalidColorJsonException::class, 'schema version'];
        yield 'other version' => ['{"v":2,"space":"srgb","coords":[0,0,0],"alpha":1}', InvalidColorJsonException::class, 'schema version'];
        yield 'version as string' => ['{"v":"1","space":"srgb","coords":[0,0,0],"alpha":1}', InvalidColorJsonException::class, 'schema version'];
        yield 'space not a string' => ['{"v":1,"space":1,"coords":[0,0,0],"alpha":1}', InvalidColorJsonException::class, '"space" must be'];
        yield 'missing alpha' => ['{"v":1,"space":"srgb","coords":[0,0,0]}', InvalidColorJsonException::class, 'are required'];
        yield 'missing coords' => ['{"v":1,"space":"srgb","alpha":1}', InvalidColorJsonException::class, 'are required'];
        yield 'coords not a list' => ['{"v":1,"space":"srgb","coords":{"r":0},"alpha":1}', InvalidColorJsonException::class, '"coords" must be'];
        yield 'coords a number' => ['{"v":1,"space":"srgb","coords":0,"alpha":1}', InvalidColorJsonException::class, '"coords" must be'];
        yield 'coordinate string' => ['{"v":1,"space":"srgb","coords":[0,"1",0],"alpha":1}', InvalidColorJsonException::class, 'Every coordinate must be a number or null, string given'];
        yield 'alpha bool' => ['{"v":1,"space":"srgb","coords":[0,0,0],"alpha":true}', InvalidColorJsonException::class, '"alpha" must be a number or null, bool given'];
        yield 'wrong arity' => ['{"v":1,"space":"srgb","coords":[0,0],"alpha":1}', InvalidColorValueException::class, 'expects 3 coordinates'];
        yield 'alpha out of range' => ['{"v":1,"space":"srgb","coords":[0,0,0],"alpha":2}', InvalidColorValueException::class, 'Alpha must be'];
        yield 'unknown space' => ['{"v":1,"space":"cmyk","coords":[0,0,0],"alpha":1}', UnknownColorSpaceException::class, '"cmyk"'];
    }

    /**
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('invalidJson')]
    public function testInvalidJson(string $json, string $exception, string $message): void
    {
        $this->expectException($exception);
        $this->expectExceptionMessage($message);

        (new JsonNotation())->decode($json);
    }

    public function testJsonSyntaxErrorKeepsThePreviousException(): void
    {
        try {
            (new JsonNotation())->decode('{');
            self::fail('Must fail.');
        } catch (InvalidColorJsonException $exception) {
            self::assertInstanceOf(\JsonException::class, $exception->getPrevious());
            self::assertSame(0, $exception->getCode());
        }
    }

    public function testColorValueValidation(): void
    {
        $srgb = ColorSpaces::srgb();

        self::assertFalse((new ColorValue($srgb, [0.0, 0.0, 0.0]))->hasMissingComponents());
        self::assertTrue((new ColorValue($srgb, [0.0, null, 0.0]))->hasMissingComponents());
        self::assertTrue((new ColorValue($srgb, [0.0, 0.0, 0.0], null))->hasMissingComponents());
        self::assertSame(1.0, (new ColorValue($srgb, [0.0, 0.0, 0.0]))->alpha);
        self::assertSame(0.0, (new ColorValue($srgb, [0.0, 0.0, 0.0], 0.0))->alpha);

        foreach ([
            'arity' => static fn(): ColorValue => new ColorValue($srgb, [0.0, 0.0]),
            // @phpstan-ignore argument.type (deliberately invalid)
            'list' => static fn(): ColorValue => new ColorValue($srgb, [1 => 0.0, 2 => 0.0, 3 => 0.0]),
            'NaN' => static fn(): ColorValue => new ColorValue($srgb, [0.0, \NAN, 0.0]),
            'INF' => static fn(): ColorValue => new ColorValue($srgb, [\INF, 0.0, 0.0]),
            'alpha above 1' => static fn(): ColorValue => new ColorValue($srgb, [0.0, 0.0, 0.0], 1.5),
            'alpha below 0' => static fn(): ColorValue => new ColorValue($srgb, [0.0, 0.0, 0.0], -0.1),
            'alpha NaN' => static fn(): ColorValue => new ColorValue($srgb, [0.0, 0.0, 0.0], \NAN),
        ] as $case => $create) {
            try {
                $create();
                self::fail($case . ' must be rejected.');
            } catch (InvalidColorValueException) {
                $this->addToAssertionCount(1);
            }
        }

        self::assertSame([1.0, 0.0, null], (new ColorValue($srgb, [1, 0, null]))->coordinates, 'integers become floats');
    }
}
