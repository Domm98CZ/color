<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Fluent;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Contrast\Contract\ContrastAlgorithm;
use Domm98CZ\Color\Contrast\Contrast;
use Domm98CZ\Color\Contrast\ContrastAdjuster;
use Domm98CZ\Color\Contrast\Result\ContrastMeasurement;
use Domm98CZ\Color\Fluent\Exception\MappingRequiredException;
use Domm98CZ\Color\Fluent\Result\FluentStep;
use Domm98CZ\Color\Fluent\Result\FluentTrace;
use Domm98CZ\Color\Harmony\Contract\HueWheel;
use Domm98CZ\Color\Harmony\Harmony;
use Domm98CZ\Color\Harmony\HarmonyScheme;
use Domm98CZ\Color\Harmony\Result\HarmonyMember;
use Domm98CZ\Color\Harmony\Result\HarmonySet;
use Domm98CZ\Color\Notation\CssFormat;
use Domm98CZ\Color\Operation\Adjuster;
use Domm98CZ\Color\Operation\ChromaModel;
use Domm98CZ\Color\Operation\Exception\InvalidOperationException;
use Domm98CZ\Color\Operation\HueModel;
use Domm98CZ\Color\Operation\LightnessModel;
use Domm98CZ\Color\Operation\Mixer;
use Domm98CZ\Color\Operation\TintShadeTone;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Provenance\Diagnostics;
use Domm98CZ\Color\Space\Record\Hsl;
use Domm98CZ\Color\Space\Record\Rgb255;

/**
 * An immutable color in a fluent chain. Every method delegates to a service of another module,
 * with the method from the {@see FluentConfig} unless the call names one, and records a
 * {@see FluentStep}.
 *
 * A chain starts from a notation constructor ({@see HexColor}, {@see RgbColor}, {@see RgbaColor},
 * {@see HslColor}, {@see OklchColor}, {@see CssColor}) or from {@see of()}; the constructors only
 * add a way to start, every operation returns a FluentColor.
 *
 * - Intermediate colors are never clipped or mapped; they stay in the space the service returns
 *   (the input space for adjustments, the mixing space for mixes).
 * - Output (`toColor()`, `toHex()`, `toCss()`, `contrastWith()`) goes through the configured output
 *   space and gamut mapper; {@see trace()} reports that mapping.
 * - Results that are not a color are the modules' own result objects (`ContrastMeasurement`,
 *   `HarmonySet`).
 */
abstract readonly class FluentColor implements \Stringable
{
    /** The luminance at which black and white text have the same WCAG contrast on the color. */
    private const float DARK_THRESHOLD = 0.179;

    /**
     * @param non-empty-list<FluentStep> $steps
     */
    protected function __construct(
        private FluentConfig $config,
        private Color $color,
        private array $steps,
    ) {}

    /**
     * @internal use {@see Fluent::color()} or {@see of()}
     *
     * @param array<string, scalar|null> $parameters
     */
    public static function start(FluentConfig $config, Color $color, array $parameters): self
    {
        return new ChainedColor($config, $color, [new FluentStep('color', $parameters, $color)]);
    }

    /**
     * A chain from an existing Color, with {@see FluentConfig::defaults()} unless a configuration is given.
     */
    public static function of(Color $color, ?FluentConfig $config = null): self
    {
        return self::start($config ?? FluentConfig::defaults(), $color, ['space' => $color->space()->id()]);
    }

    /**
     * The current color, unmapped (it may be outside every gamut).
     */
    public function color(): Color
    {
        return $this->color;
    }

    public function config(): FluentConfig
    {
        return $this->config;
    }

    public function trace(): FluentTrace
    {
        return new FluentTrace($this->steps, $this->config->outputSpace, $this->config->outputMapper?->map($this->color, $this->config->outputSpace));
    }

    /**
     * {@see Adjuster::shiftLightness()}: adds $by in the units of the model (OKLCh L 0–1, CIE L* and
     * HSL l 0–100).
     */
    public function lighten(float $by, ?LightnessModel $model = null): self
    {
        $model ??= $this->config->lightness;

        return $this->then('lighten', ['by' => $by, 'model' => $model->value], (new Adjuster())->shiftLightness($this->color, $by, $model));
    }

    /**
     * {@see Adjuster::shiftLightness()} with −$by.
     */
    public function darken(float $by, ?LightnessModel $model = null): self
    {
        $model ??= $this->config->lightness;

        return $this->then('darken', ['by' => $by, 'model' => $model->value], (new Adjuster())->shiftLightness($this->color, -$by, $model));
    }

    /**
     * {@see Adjuster::scaleChroma()}: multiplies the chroma by $scale.
     */
    public function scaleChroma(float $scale, ?ChromaModel $model = null): self
    {
        $model ??= $this->config->chroma;

        return $this->then('scaleChroma', ['scale' => $scale, 'model' => $model->value], (new Adjuster())->scaleChroma($this->color, $scale, $model));
    }

    /**
     * {@see Adjuster::rotateHue()}.
     */
    public function rotateHue(float $degrees, ?HueModel $model = null): self
    {
        $model ??= $this->config->hue;

        return $this->then('rotateHue', ['degrees' => $degrees, 'model' => $model->value], (new Adjuster())->rotateHue($this->color, $degrees, $model));
    }

    /**
     * {@see Mixer::mix()}: $weight is the amount of $other (a Color, a CSS string or another chain,
     * whose unmapped color is used).
     */
    public function mix(Color|string|self $other, float $weight = 0.5, ?Mixer $mixer = null): self
    {
        $mixer ??= $this->config->mixer;
        $other = self::toColorValue($other);

        return $this->then('mix', ['with' => $other->toCss(), 'weight' => $weight] + self::mixing($mixer), $mixer->mix($this->color, $other, $weight));
    }

    /**
     * {@see TintShadeTone::tint()} in the space and hue method of the mixer.
     */
    public function tint(float $amount, ?Mixer $mixer = null): self
    {
        $mixer ??= $this->config->mixer;

        return $this->then('tint', ['amount' => $amount] + self::mixing($mixer), self::tintShadeTone($mixer)->tint($this->color, $amount));
    }

    /**
     * {@see TintShadeTone::shade()} in the space and hue method of the mixer.
     */
    public function shade(float $amount, ?Mixer $mixer = null): self
    {
        $mixer ??= $this->config->mixer;

        return $this->then('shade', ['amount' => $amount] + self::mixing($mixer), self::tintShadeTone($mixer)->shade($this->color, $amount));
    }

    /**
     * {@see TintShadeTone::tone()} in the space and hue method of the mixer; the default gray has
     * the OKLab lightness of the color.
     */
    public function tone(float $amount, Color|string|self|null $gray = null, ?Mixer $mixer = null): self
    {
        $mixer ??= $this->config->mixer;
        $gray = $gray === null ? null : self::toColorValue($gray);

        return $this->then('tone', ['amount' => $amount, 'gray' => $gray?->toCss()] + self::mixing($mixer), self::tintShadeTone($mixer)->tone($this->color, $amount, $gray));
    }

    /**
     * $steps colors between this color and white (both excluded), lightest last; {@see tint()} per step.
     *
     * @return list<self>
     *
     * @throws InvalidOperationException when $steps is below 1
     */
    public function tints(int $steps, ?Mixer $mixer = null): array
    {
        return \array_map(fn(float $amount): self => $this->tint($amount, $mixer), self::fractions($steps));
    }

    /**
     * $steps colors between this color and black (both excluded), darkest last; {@see shade()} per step.
     *
     * @return list<self>
     *
     * @throws InvalidOperationException when $steps is below 1
     */
    public function shades(int $steps, ?Mixer $mixer = null): array
    {
        return \array_map(fn(float $amount): self => $this->shade($amount, $mixer), self::fractions($steps));
    }

    /**
     * $steps colors between this color and the gray (both excluded); {@see tone()} per step.
     *
     * @return list<self>
     *
     * @throws InvalidOperationException when $steps is below 1
     */
    public function tones(int $steps, Color|string|self|null $gray = null, ?Mixer $mixer = null): array
    {
        return \array_map(fn(float $amount): self => $this->tone($amount, $gray, $mixer), self::fractions($steps));
    }

    /**
     * {@see Color::withAlpha()}; null makes the alpha missing.
     */
    public function withAlpha(?float $alpha): self
    {
        return $this->then('withAlpha', ['alpha' => $alpha], $this->color->withAlpha($alpha));
    }

    /**
     * The complementary color on the wheel ({@see Harmony::complementary()}). A color without a hue
     * stays the same, with a `hue-undefined` warning in the step.
     */
    public function complement(?HueWheel $wheel = null): self
    {
        $wheel ??= $this->config->wheel;
        $set = Harmony::complementary($this->color, $wheel, $this->config->outputSpace);

        return $this->then('complement', ['wheel' => $wheel->id()->identifier()], $set->members[1]->color, $wheel->id(), $set->diagnostics);
    }

    /**
     * {@see Harmony::of()} with the configured wheel; members are checked against the output space.
     */
    public function harmony(HarmonyScheme $scheme, ?HueWheel $wheel = null): HarmonySet
    {
        return Harmony::of($this->color, $wheel ?? $this->config->wheel, $scheme, $this->config->outputSpace);
    }

    /**
     * The three colors 120° apart on the wheel as chains, this color first ({@see harmony()} gives
     * the full HarmonySet).
     *
     * @return list<self>
     */
    public function triadic(?HueWheel $wheel = null): array
    {
        return $this->members(HarmonyScheme::triadic(), $wheel);
    }

    /**
     * $count neighbours $step degrees apart around this color, as chains ({@see HarmonyScheme::analogous()}).
     *
     * @return list<self>
     */
    public function analogous(int $count = 3, float $step = 30.0, ?HueWheel $wheel = null): array
    {
        return $this->members(HarmonyScheme::analogous($count, $step), $wheel);
    }

    /**
     * This color and the two neighbours of its complement, as chains ({@see HarmonyScheme::splitComplementary()}).
     *
     * @return list<self>
     */
    public function splitComplementary(float $spread = 30.0, ?HueWheel $wheel = null): array
    {
        return $this->members(HarmonyScheme::splitComplementary($spread), $wheel);
    }

    /**
     * Contrast of this color (foreground) on $background, both after the output conversion (what
     * would be displayed); with the default WCAG 2 algorithm and mapping switched off, colors
     * outside sRGB throw.
     */
    public function contrastWith(Color|string|self $background, ?ContrastAlgorithm $algorithm = null): ContrastMeasurement
    {
        return ($algorithm ?? $this->config->contrast)->measure($this->output($this->color), $this->output(self::toColorValue($background)));
    }

    /**
     * This color as text on $background, moved along the configured lightness model (hue and
     * chroma kept) until the configured contrast reaches $target
     * ({@see ContrastAdjuster::adjustForeground()}); the result is in sRGB. An unreachable target
     * leaves the best candidate with a `contrast-target-unreachable` warning in the step.
     *
     * @throws MappingRequiredException when output mapping is switched off
     */
    public function readableOn(Color|string|self $background, float $target): self
    {
        $fixed = $this->output(self::toColorValue($background));
        $adjuster = $this->adjuster('readableOn');
        $result = $adjuster->adjustForeground($this->color, $fixed, $target);

        return $this->then('readableOn', ['on' => $fixed->toCss(), 'target' => $target] + $this->adjusting(), $result->color, $adjuster->id(), $result->diagnostics);
    }

    /**
     * This color as the background behind $foreground ({@see ContrastAdjuster::adjustBackground()});
     * otherwise as {@see readableOn()}.
     *
     * @throws MappingRequiredException when output mapping is switched off
     */
    public function readableBehind(Color|string|self $foreground, float $target): self
    {
        $fixed = $this->output(self::toColorValue($foreground));
        $adjuster = $this->adjuster('readableBehind');
        $result = $adjuster->adjustBackground($this->color, $fixed, $target);

        return $this->then('readableBehind', ['behind' => $fixed->toCss(), 'target' => $target] + $this->adjusting(), $result->color, $adjuster->id(), $result->diagnostics);
    }

    /**
     * WCAG 2 relative luminance of the output color (0 black … 1 white); WCAG defines it, so it is
     * not configurable.
     */
    public function luminance(): float
    {
        return Contrast::wcag2()->relativeLuminance($this->toColor());
    }

    /**
     * Whether the luminance is below $threshold; the default 0.179 is the luminance at which black
     * and white text have the same WCAG contrast on the color.
     */
    public function isDark(?float $threshold = null): bool
    {
        return $this->luminance() < ($threshold ?? self::DARK_THRESHOLD);
    }

    public function isLight(?float $threshold = null): bool
    {
        return !$this->isDark($threshold);
    }

    /**
     * The color in the output space, gamut-mapped by the configured mapper (only converted when
     * mapping is off).
     */
    public function toColor(): Color
    {
        return $this->output($this->color);
    }

    /**
     * {@see Color::toHex()} of {@see toColor()}.
     */
    public function toHex(bool $alwaysIncludeAlpha = false): string
    {
        return $this->toColor()->toHex($alwaysIncludeAlpha);
    }

    /**
     * {@see Color::toCss()} of {@see toColor()}: the notation of the output space (rgb() for sRGB).
     */
    public function toCss(?CssFormat $format = null): string
    {
        return $this->toColor()->toCss($format);
    }

    /**
     * {@see toColor()} as sRGB components 0–255.
     */
    public function toRgb(): Rgb255
    {
        return $this->toColor()->in(Rgb255::class);
    }

    /**
     * {@see toColor()} as HSL (hue in degrees, saturation and lightness 0–100).
     */
    public function toHsl(): Hsl
    {
        return $this->toColor()->in(Hsl::class);
    }

    public function __toString(): string
    {
        return $this->toHex();
    }

    /**
     * @param non-empty-string $operation
     * @param array<string, scalar|null> $parameters
     */
    private function then(string $operation, array $parameters, Color $result, ?AlgorithmId $algorithm = null, ?Diagnostics $diagnostics = null): self
    {
        return new ChainedColor($this->config, $result, [...$this->steps, new FluentStep($operation, $parameters, $result, $algorithm, $diagnostics)]);
    }

    /**
     * @return list<self>
     */
    private function members(HarmonyScheme $scheme, ?HueWheel $wheel): array
    {
        $wheel ??= $this->config->wheel;
        $set = $this->harmony($scheme, $wheel);

        return \array_map(
            fn(HarmonyMember $member): self => $this->then('harmony', ['scheme' => $scheme->name, 'offset' => $member->offset, 'wheel' => $wheel->id()->identifier()], $member->color, $wheel->id(), $set->diagnostics),
            $set->members,
        );
    }

    /**
     * k / (steps + 1) for k = 1 … steps: the end points (the color itself and white, black or gray)
     * are not part of the scale.
     *
     * @return list<float>
     *
     * @throws InvalidOperationException
     */
    private static function fractions(int $steps): array
    {
        if ($steps < 1) {
            throw new InvalidOperationException(\sprintf('A scale needs at least one step, %d given.', $steps));
        }

        return \array_map(static fn(int $k): float => $k / ($steps + 1), \range(1, $steps));
    }

    private function output(Color $color): Color
    {
        $mapper = $this->config->outputMapper;

        return $mapper === null ? $color->to($this->config->outputSpace) : $mapper->map($color, $this->config->outputSpace)->color;
    }

    /**
     * @throws MappingRequiredException
     */
    private function adjuster(string $method): ContrastAdjuster
    {
        return new ContrastAdjuster($this->config->contrast, $this->config->lightness, $this->config->outputMapper ?? throw MappingRequiredException::for($method));
    }

    /**
     * The configuration an adjustment step used; only called after {@see adjuster()} succeeded.
     *
     * @return array{contrast: string, lightness: string, mapper: string}
     */
    private function adjusting(): array
    {
        return [
            'contrast' => $this->config->contrast->id()->identifier(),
            'lightness' => $this->config->lightness->value,
            'mapper' => $this->config->outputMapper?->id()->identifier() ?? '',
        ];
    }

    private static function toColorValue(Color|string|self $color): Color
    {
        return match (true) {
            $color instanceof Color => $color,
            $color instanceof self => $color->color(),
            default => Color::parse($color),
        };
    }

    /**
     * @return array{space: string, hue: string}
     */
    private static function mixing(Mixer $mixer): array
    {
        return ['space' => $mixer->method->space->id(), 'hue' => $mixer->method->hue->value];
    }

    private static function tintShadeTone(Mixer $mixer): TintShadeTone
    {
        return new TintShadeTone($mixer->method->space, $mixer->method->hue);
    }
}
