<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Fluent;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Fluent\ChainedColor;
use Domm98CZ\Color\Fluent\CssColor;
use Domm98CZ\Color\Fluent\FluentColor;
use Domm98CZ\Color\Fluent\FluentConfig;
use Domm98CZ\Color\Fluent\HexColor;
use Domm98CZ\Color\Fluent\HslColor;
use Domm98CZ\Color\Fluent\OklchColor;
use Domm98CZ\Color\Fluent\RgbaColor;
use Domm98CZ\Color\Fluent\RgbColor;
use Domm98CZ\Color\Notation\Exception\ColorParseException;
use Domm98CZ\Color\Notation\Exception\OutOfGamutException;
use Domm98CZ\Color\Space\Exception\InvalidColorValueException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The notation constructors start exactly the chain that the matching Color factory would.
 */
final class NotationColorsTest extends TestCase
{
    /**
     * @return iterable<string, array{FluentColor, Color, array<string, scalar|null>}>
     */
    public static function notations(): iterable
    {
        yield 'hex' => [new HexColor('#3366cc'), Color::fromHex('#3366cc'), ['hex' => '#3366cc']];
        yield 'rgb' => [new RgbColor(51, 102, 204), Color::fromRgb(51, 102, 204), ['red' => 51, 'green' => 102, 'blue' => 204]];
        yield 'rgba' => [new RgbaColor(51, 102, 204, 0.5), Color::fromRgba(51, 102, 204, 0.5), ['red' => 51, 'green' => 102, 'blue' => 204, 'alpha' => 0.5]];
        yield 'hsl' => [new HslColor(220, 60, 50), Color::fromHsl(220, 60, 50), ['hue' => 220.0, 'saturation' => 60.0, 'lightness' => 50.0, 'alpha' => 1.0]];
        yield 'oklch' => [new OklchColor(0.6, 0.15, 260), Color::fromOklch(0.6, 0.15, 260), ['lightness' => 0.6, 'chroma' => 0.15, 'hue' => 260.0, 'alpha' => 1.0]];
        yield 'css' => [new CssColor('rebeccapurple'), Color::parse('rebeccapurple'), ['css' => 'rebeccapurple']];
    }

    /**
     * @param array<string, scalar|null> $parameters
     */
    #[DataProvider('notations')]
    public function testTheConstructorEqualsTheColorFactory(FluentColor $chain, Color $expected, array $parameters): void
    {
        self::assertTrue($chain->color()->equals($expected), $chain->color()->toCss() . ' vs ' . $expected->toCss());
        self::assertSame(FluentConfig::defaults()->outputSpace, $chain->config()->outputSpace);

        $steps = $chain->trace()->steps;
        self::assertCount(1, $steps);
        self::assertSame('color', $steps[0]->operation);
        self::assertSame($parameters, $steps[0]->parameters);
    }

    public function testAConfigurationIsUsed(): void
    {
        $strict = FluentConfig::defaults()->withOutputMapping(null);
        $chain = new HexColor('#3366cc', $strict);

        self::assertSame($strict, $chain->config());

        $this->expectException(OutOfGamutException::class);
        $chain->lighten(0.3)->scaleChroma(1.5)->toHex();
    }

    public function testOperationsReturnTheBaseClass(): void
    {
        $lighter = (new HexColor('#3366cc'))->lighten(0.1);

        self::assertInstanceOf(ChainedColor::class, $lighter);
        self::assertTrue($lighter->color()->equals(FluentColor::of(Color::fromHex('#3366cc'))->lighten(0.1)->color()));
    }

    public function testOf(): void
    {
        $chain = FluentColor::of(Color::fromHex('#3366cc'));

        self::assertSame('#3366cc', $chain->toHex());
        self::assertSame(['space' => 'srgb'], $chain->trace()->steps[0]->parameters);
        self::assertNull(FluentColor::of(Color::fromHex('#3366cc'), FluentConfig::defaults()->withOutputMapping(null))->config()->outputMapper);
    }

    public function testAnInvalidHexIsRejected(): void
    {
        $this->expectException(ColorParseException::class);
        new HexColor('nope');
    }

    public function testAnInvalidRgbComponentIsRejected(): void
    {
        $this->expectException(InvalidColorValueException::class);
        new RgbColor(256, 0, 0);
    }
}
