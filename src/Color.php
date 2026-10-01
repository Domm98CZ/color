<?php

declare(strict_types=1);

namespace Domm98CZ\Color;

use Domm98CZ\Color\Math\Angle;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Notation\CssFormat;
use Domm98CZ\Color\Notation\CssParser;
use Domm98CZ\Color\Notation\CssSerializer;
use Domm98CZ\Color\Notation\Exception\ColorParseException;
use Domm98CZ\Color\Notation\Exception\InvalidColorJsonException;
use Domm98CZ\Color\Notation\Exception\OutOfGamutException;
use Domm98CZ\Color\Notation\Exception\UnresolvableCssColorException;
use Domm98CZ\Color\Notation\HexNotation;
use Domm98CZ\Color\Notation\JsonNotation;
use Domm98CZ\Color\Space\ColorSpaceRegistry;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\ColorValue;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\ConversionOptions;
use Domm98CZ\Color\Space\Converter;
use Domm98CZ\Color\Space\Exception\InvalidColorValueException;
use Domm98CZ\Color\Space\Exception\UnknownChannelException;
use Domm98CZ\Color\Space\Exception\UnknownColorSpaceException;
use Domm98CZ\Color\Space\Internal\Coordinates;
use Domm98CZ\Color\Space\Record\CoordinateRecord;

/**
 * A colorimetrically defined digital color: a color space, its coordinates and an alpha.
 *
 * - Immutable. Every "change" returns a new instance.
 * - Coordinates are float64 and never clipped; values outside the gamut are valid.
 * - A coordinate may be missing (null, CSS `none`); a missing component behaves as zero in
 *   conversions (CSS Color 4 § 4.4). After conversion into a cylindrical space, a powerless hue
 *   is null.
 * - The color keeps the space it was created in; {@see to()} returns a new color in another space.
 *
 * Operations that depend on a method choice (mixing, lightening, contrast, harmonies) are not
 * methods of this class; they live in their modules where the method is explicit.
 */
final readonly class Color
{
    /**
     * Default tolerance of {@see isInGamut()}, in the units of the RGB gamut space (0–1).
     * Far above round-trip error (~1e-12), far below the 16-bit quantisation step (1.5e-5):
     * an sRGB color converted to OKLCh and back does not fall out of the sRGB gamut.
     * Part of the compatibility promise.
     */
    public const float DEFAULT_GAMUT_TOLERANCE = 1e-6;

    /**
     * @param list<float|null> $coordinates validated and normalised
     */
    private function __construct(
        private ColorSpace $space,
        private array $coordinates,
        private ?float $alpha,
    ) {}

    // ------------------------------------------------------------------ creation

    /**
     * @param array<array-key, mixed> $coordinates exactly one number or null (missing) per channel
     * @param float|null $alpha 0–1, or null for a missing alpha (CSS `none`)
     *
     * @throws InvalidColorValueException
     */
    public static function of(ColorSpace $space, array $coordinates, ?float $alpha = 1.0): self
    {
        return new self($space, Coordinates::normalize($space, $coordinates), $alpha === null ? null : Coordinates::alpha($alpha));
    }

    public static function from(CoordinateRecord $record): self
    {
        return self::of($record::space(), $record->coordinates(), $record->alpha());
    }

    /**
     * sRGB from 8-bit components 0–255 (as CSS `rgb(51 102 204)`). Strict: other values are errors.
     *
     * @throws InvalidColorValueException
     */
    public static function fromRgb(int $red, int $green, int $blue): self
    {
        return self::fromRgba($red, $green, $blue, 1.0);
    }

    /**
     * sRGB from 8-bit components 0–255 and alpha 0–1 (as CSS `rgb(51 102 204 / 0.5)`).
     *
     * @throws InvalidColorValueException
     */
    public static function fromRgba(int $red, int $green, int $blue, float $alpha): self
    {
        foreach (['red' => $red, 'green' => $green, 'blue' => $blue] as $name => $value) {
            if ($value < 0 || $value > 255) {
                throw new InvalidColorValueException(\sprintf('The %s component must be an integer 0–255, %d given.', $name, $value));
            }
        }

        return self::of(ColorSpaces::srgb(), [$red / 255, $green / 255, $blue / 255], $alpha);
    }

    /**
     * sRGB from components on the 0–1 scale (as CSS `color(srgb r g b)`); out-of-gamut values allowed.
     */
    public static function fromSrgb(float $red, float $green, float $blue, float $alpha = 1.0): self
    {
        return self::of(ColorSpaces::srgb(), [$red, $green, $blue], $alpha);
    }

    /**
     * HSL over sRGB with the scales of CSS `hsl()`: hue in degrees, saturation and lightness 0–100.
     */
    public static function fromHsl(float $hue, float $saturation, float $lightness, float $alpha = 1.0): self
    {
        return self::of(ColorSpaces::hsl(), [$hue, $saturation, $lightness], $alpha);
    }

    /**
     * HWB over sRGB with the scales of CSS `hwb()`: hue in degrees, whiteness and blackness 0–100.
     */
    public static function fromHwb(float $hue, float $whiteness, float $blackness, float $alpha = 1.0): self
    {
        return self::of(ColorSpaces::hwb(), [$hue, $whiteness, $blackness], $alpha);
    }

    /**
     * HSV/HSB over sRGB: hue in degrees, saturation and value 0–100.
     */
    public static function fromHsv(float $hue, float $saturation, float $value, float $alpha = 1.0): self
    {
        return self::of(ColorSpaces::hsv(), [$hue, $saturation, $value], $alpha);
    }

    /**
     * CIE Lab relative to D50 (as CSS `lab()`): L* 0–100.
     */
    public static function fromLab(float $lightness, float $a, float $b, float $alpha = 1.0): self
    {
        return self::of(ColorSpaces::lab(), [$lightness, $a, $b], $alpha);
    }

    /**
     * CIE LCh relative to D50 (as CSS `lch()`): L* 0–100, chroma, hue in degrees.
     */
    public static function fromLch(float $lightness, float $chroma, float $hue, float $alpha = 1.0): self
    {
        return self::of(ColorSpaces::lch(), [$lightness, $chroma, $hue], $alpha);
    }

    /**
     * OKLab (as CSS `oklab()`): L 0–1.
     */
    public static function fromOklab(float $lightness, float $a, float $b, float $alpha = 1.0): self
    {
        return self::of(ColorSpaces::oklab(), [$lightness, $a, $b], $alpha);
    }

    /**
     * OKLCh (as CSS `oklch()`): L 0–1, chroma, hue in degrees.
     */
    public static function fromOklch(float $lightness, float $chroma, float $hue, float $alpha = 1.0): self
    {
        return self::of(ColorSpaces::oklch(), [$lightness, $chroma, $hue], $alpha);
    }

    /**
     * CIE XYZ relative to D65, white at Y = 1 (as CSS `color(xyz-d65 x y z)`).
     */
    public static function fromXyz(float $x, float $y, float $z, float $alpha = 1.0): self
    {
        return self::of(ColorSpaces::xyzD65(), [$x, $y, $z], $alpha);
    }

    /**
     * sRGB from a hex color: 3, 4, 6 or 8 hexadecimal digits, optional "#" (e.g. '#3366cc', 'fff8').
     *
     * @throws ColorParseException
     */
    public static function fromHex(string $hex): self
    {
        return self::fromValue((new HexNotation())->parse($hex));
    }

    /**
     * A CSS Color 4 `<color>` value, e.g. 'rebeccapurple', '#36c', 'rgb(51 102 204 / 50%)',
     * 'oklch(62% 0.18 255)', 'color(display-p3 1 0 0)'. The color keeps the space of the syntax
     * (`hsl()` gives an `hsl` color). Clamping defined by CSS (e.g. `rgb(300 0 0)`) is applied.
     * See {@see CssParser} for what is not supported.
     *
     * @throws ColorParseException invalid or unsupported syntax
     * @throws UnresolvableCssColorException currentcolor, system colors, light-dark()
     */
    public static function parse(string $css): self
    {
        return self::fromValue((new CssParser())->parse($css));
    }

    /**
     * A color from its JSON form ({@see toJson()}); the space id is looked up in $registry
     * (default: the built-in spaces).
     *
     * @throws InvalidColorJsonException
     * @throws UnknownColorSpaceException
     * @throws InvalidColorValueException
     */
    public static function fromJson(string $json, ?ColorSpaceRegistry $registry = null): self
    {
        return self::fromValue((new JsonNotation($registry))->decode($json));
    }

    // ------------------------------------------------------------------ reading

    public function space(): ColorSpace
    {
        return $this->space;
    }

    /**
     * @return list<float|null> null = missing component (or powerless hue)
     */
    public function coordinates(): array
    {
        return $this->coordinates;
    }

    /**
     * Coordinate by channel index or channel name (e.g. 'l', 'c', 'h').
     *
     * @throws UnknownChannelException
     */
    public function coordinate(int|string $channel): ?float
    {
        return $this->coordinates[$this->channelIndex($channel)];
    }

    /**
     * Alpha in [0, 1]. A missing alpha behaves as zero, as every missing component does
     * (CSS Color 4 § 4.4); use {@see isAlphaMissing()} to distinguish it.
     */
    public function alpha(): float
    {
        return $this->alpha ?? 0.0;
    }

    public function isAlphaMissing(): bool
    {
        return $this->alpha === null;
    }

    public function hasMissingComponents(): bool
    {
        return \in_array(null, $this->coordinates, true) || $this->alpha === null;
    }

    /**
     * Typed view of this color in the record's space, e.g. `$color->in(Oklch::class)->c`.
     *
     * @template T of CoordinateRecord
     *
     * @param class-string<T> $record
     *
     * @return T
     */
    public function in(string $record, ?ConversionOptions $options = null): CoordinateRecord
    {
        return $record::fromCoordinates($this->to($record::space(), $options)->coordinates, $this->alpha());
    }

    // ------------------------------------------------------------------ conversion and gamut

    /**
     * The same color in another space. Never clips and never maps the gamut. Converting to the
     * space the color is already in returns it unchanged (missing components included).
     */
    public function to(ColorSpace $space, ?ConversionOptions $options = null): self
    {
        if ($space->id() === $this->space->id()) {
            return $this;
        }

        $converted = (new Converter($options ?? new ConversionOptions()))->convert($this->space, $space, $this->coordinates);

        return new self($space, Coordinates::normalize($space, $converted), $this->alpha);
    }

    /**
     * Whether the color lies inside the gamut of $space (default: its own space), within the tolerance.
     * The gamut is the [0, 1] cube of the space's RGB gamut space ({@see ColorSpace::gamutSpace()});
     * spaces without a gamut boundary (XYZ, Lab, OKLab…) contain every color.
     */
    public function isInGamut(?ColorSpace $space = null, ?Tolerance $tolerance = null): bool
    {
        $gamutSpace = ($space ?? $this->space)->gamutSpace();

        if ($gamutSpace === null) {
            return true;
        }

        $bound = ($tolerance ?? Tolerance::absolute(self::DEFAULT_GAMUT_TOLERANCE))->absolute;
        $coordinates = $this->to($gamutSpace)->coordinates;

        foreach ($gamutSpace->channels() as $index => $channel) {
            $value = $coordinates[$index] ?? 0.0;

            if ($value < $channel->referenceMin - $bound || $value > $channel->referenceMax + $bound) {
                return false;
            }
        }

        return true;
    }

    // ------------------------------------------------------------------ low-level changes

    /**
     * @throws InvalidColorValueException when alpha is outside [0, 1]
     */
    public function withAlpha(?float $alpha): self
    {
        return new self($this->space, $this->coordinates, $alpha === null ? null : Coordinates::alpha($alpha));
    }

    public function opaque(): self
    {
        return new self($this->space, $this->coordinates, 1.0);
    }

    /**
     * Replaces one coordinate in the color's own space; no conversion is involved.
     *
     * @throws UnknownChannelException
     * @throws InvalidColorValueException
     */
    public function withCoordinate(int|string $channel, ?float $value): self
    {
        $coordinates = $this->coordinates;
        $coordinates[$this->channelIndex($channel)] = $value;

        return self::of($this->space, $coordinates, $this->alpha);
    }

    // ------------------------------------------------------------------ comparison

    /**
     * Exact equality: same space id, identical coordinates (missing = missing), identical alpha.
     * Use {@see isCloseTo()} for numeric closeness; perceptual sameness is a color-difference metric.
     */
    public function equals(self $other): bool
    {
        return $this->space->id() === $other->space->id()
            && $this->coordinates === $other->coordinates
            && $this->alpha === $other->alpha;
    }

    /**
     * Numeric closeness in the space of $this: $other is converted into it first. Hue channels are
     * compared as angles (absolute tolerance, degrees) and ignored when either hue is missing
     * (achromatic colors); missing non-hue components compare as zero; alpha is compared too.
     */
    public function isCloseTo(self $other, Tolerance $tolerance): bool
    {
        $theirs = $other->to($this->space)->coordinates;

        foreach ($this->space->channels() as $index => $channel) {
            $mine = $this->coordinates[$index];
            $their = $theirs[$index];

            if ($channel->isHue()) {
                if ($mine !== null && $their !== null && Angle::distanceDegrees($mine, $their) > $tolerance->absolute) {
                    return false;
                }

                continue;
            }

            if (!$tolerance->isClose($mine ?? 0.0, $their ?? 0.0)) {
                return false;
            }
        }

        return $tolerance->isClose($this->alpha(), $other->alpha());
    }

    // ------------------------------------------------------------------ serialisation

    /**
     * `#rrggbb`, or `#rrggbbaa` when the alpha is not 1 (or when $alwaysIncludeAlpha). The color is
     * converted to sRGB; it must be inside the sRGB gamut, nothing is clipped silently.
     *
     * @throws OutOfGamutException
     */
    public function toHex(bool $alwaysIncludeAlpha = false): string
    {
        return (new HexNotation())->format($this->value(), $alwaysIncludeAlpha);
    }

    /**
     * CSS `<color>` in the color's own space where CSS has one (`oklch(…)`, `lab(…)`,
     * `color(display-p3 …)`; sRGB, HSL and HWB colors as `rgb()`/`rgba()`). The result parses back
     * to the same color up to the precision of $format. See {@see CssSerializer} for all rules.
     */
    public function toCss(?CssFormat $format = null): string
    {
        return (new CssSerializer($format ?? new CssFormat()))->serialize($this->value());
    }

    /**
     * Lossless JSON form, e.g. `{"v":1,"space":"oklch","coords":[0.7,0.12,250],"alpha":1}`.
     */
    public function toJson(): string
    {
        return (new JsonNotation())->encode($this->value());
    }

    private function value(): ColorValue
    {
        return new ColorValue($this->space, $this->coordinates, $this->alpha);
    }

    private static function fromValue(ColorValue $value): self
    {
        return self::of($value->space, $value->coordinates, $value->alpha);
    }

    private function channelIndex(int|string $channel): int
    {
        foreach ($this->space->channels() as $index => $definition) {
            if ($channel === $index || $channel === $definition->name) {
                return $index;
            }
        }

        throw UnknownChannelException::in($this->space->id(), $channel);
    }
}
