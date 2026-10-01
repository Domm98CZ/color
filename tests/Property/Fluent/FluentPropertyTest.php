<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Property\Fluent;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Fluent\Fluent;
use Domm98CZ\Color\Fluent\FluentConfig;
use Domm98CZ\Color\Gamut\GamutMappers;
use Domm98CZ\Color\Harmony\Harmony;
use Domm98CZ\Color\Harmony\HueWheels;
use Domm98CZ\Color\Operation\Adjuster;
use Domm98CZ\Color\Operation\ChromaModel;
use Domm98CZ\Color\Operation\HueModel;
use Domm98CZ\Color\Operation\LightnessModel;
use Domm98CZ\Color\Operation\Mixer;
use Domm98CZ\Color\Operation\TintShadeTone;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Support\SeededRandom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Random chains of fluent steps give exactly the colors of the same sequence of direct service
 * calls, and the trace lists every step.
 */
final class FluentPropertyTest extends TestCase
{
    private const int ITERATIONS = 100;

    private const int MAX_STEPS = 6;

    /**
     * @return iterable<string, array{FluentConfig}>
     */
    public static function configs(): iterable
    {
        yield 'defaults' => [FluentConfig::defaults()];
        yield 'hsl-like' => [FluentConfig::defaults()->withLightness(LightnessModel::HslL)->withHue(HueModel::Hsl)->withMixer(new Mixer(ColorSpaces::srgb()))->withWheel(HueWheels::hsl())];
        yield 'cie' => [FluentConfig::defaults()->withLightness(LightnessModel::CieLabL)->withChroma(ChromaModel::CieLch)->withHue(HueModel::CieLch)->withMixer(new Mixer(ColorSpaces::lab()))->withWheel(HueWheels::cieLch())->withOutputSpace(ColorSpaces::displayP3())->withOutputMapping(GamutMappers::rayTrace())];
    }

    #[DataProvider('configs')]
    public function testChainsEqualDirectCalls(FluentConfig $config): void
    {
        $random = SeededRandom::fromEnvironment();
        $fluent = new Fluent($config);
        $adjuster = new Adjuster();
        $tints = new TintShadeTone($config->mixer->method->space, $config->mixer->method->hue);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $color = Color::fromOklch($random->float(0.05, 0.95), $random->float(0.0, 0.3), $random->float(0.0, 360.0), $random->float(0.2, 1.0));
            $chain = $fluent->color($color);
            $expected = $color;
            $operations = ['color'];

            for ($k = $random->int(1, self::MAX_STEPS); $k > 0; $k--) {
                $x = $random->float(0.0, 1.0);
                $other = Color::fromSrgb($random->float(0.0, 1.0), $random->float(0.0, 1.0), $random->float(0.0, 1.0));
                [$operation, $chain, $expected] = match ($random->int(0, 8)) {
                    0 => ['lighten', $chain->lighten($x * $config->lightness->maximum() / 4), $adjuster->shiftLightness($expected, $x * $config->lightness->maximum() / 4, $config->lightness)],
                    1 => ['darken', $chain->darken($x * $config->lightness->maximum() / 4), $adjuster->shiftLightness($expected, -$x * $config->lightness->maximum() / 4, $config->lightness)],
                    2 => ['scaleChroma', $chain->scaleChroma(2 * $x), $adjuster->scaleChroma($expected, 2 * $x, $config->chroma)],
                    3 => ['rotateHue', $chain->rotateHue(360 * $x - 180), $adjuster->rotateHue($expected, 360 * $x - 180, $config->hue)],
                    4 => ['mix', $chain->mix($other, $x), $config->mixer->mix($expected, $other, $x)],
                    5 => ['tint', $chain->tint($x), $tints->tint($expected, $x)],
                    6 => ['shade', $chain->shade($x), $tints->shade($expected, $x)],
                    7 => ['tone', $chain->tone($x), $tints->tone($expected, $x)],
                    default => ['complement', $chain->complement(), Harmony::complementary($expected, $config->wheel)->members[1]->color],
                };
                $operations[] = $operation;
            }

            $context = $random->describe() . ' ' . \implode(' → ', $operations);
            self::assertTrue($chain->color()->equals($expected), $context);
            self::assertSame($operations, $chain->trace()->operations(), $context);
            $mapper = $config->outputMapper;
            self::assertNotNull($mapper);
            self::assertTrue($chain->toColor()->equals($mapper->map($expected, $config->outputSpace)->color), $context);
        }
    }
}
