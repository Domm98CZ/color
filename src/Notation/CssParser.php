<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Notation;

use Domm98CZ\Color\Interpolation\ColorMix;
use Domm98CZ\Color\Interpolation\HueInterpolation;
use Domm98CZ\Color\Interpolation\InterpolationMethod;
use Domm98CZ\Color\Interpolation\MissingComponents;
use Domm98CZ\Color\Interpolation\MixItem;
use Domm98CZ\Color\Math\Angle;
use Domm98CZ\Color\Notation\Exception\ColorParseException;
use Domm98CZ\Color\Notation\Exception\UnresolvableCssColorException;
use Domm98CZ\Color\Notation\Internal\CssToken;
use Domm98CZ\Color\Notation\Internal\CssTokenizer;
use Domm98CZ\Color\Notation\Internal\CssTokenType;
use Domm98CZ\Color\Notation\Internal\HexDigits;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\ColorValue;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Parses one CSS `<color>` value (CSS Color Module Level 4, CRD 2026-09-26, and the parts of CSS
 * Color Module Level 5, WD 13 September 2026, listed below).
 *
 * Supported: hex colors, the 148 named colors, `transparent`, `rgb()`/`rgba()` and `hsl()`/`hsla()`
 * in both the modern and the legacy (comma) syntax, `hwb()`, `lab()`, `lch()`, `oklab()`, `oklch()`
 * and `color()` with the predefined color spaces; `none`, percentages and the angle units
 * `deg`, `grad`, `rad`, `turn`. Clamping at parsed-value time follows the specification
 * (e.g. `rgb(300 0 0)` is `rgb(255 0 0)`, alpha is clamped to [0, 1]); it is CSS behaviour, not an
 * error.
 *
 * CSS Color 5: `color-mix()` (§ 3, computed with {@see ColorMix}) and relative color syntax
 * (§ 4: `rgb(from <color> r g b / alpha)` and the same for every function above, and `alpha()`).
 * Channel keywords, numbers, percentages, angles and `none` are accepted in relative colors;
 * math functions are not. Relative components are not clamped (§ 4.1), the alpha is.
 *
 * The result keeps the color space of the syntax: `rgb()`, hex and named colors are `srgb`,
 * `hsl()` is `hsl`, `lab()` is `lab`, `color(xyz …)` is `xyz-d65`, `color-mix(in lch, …)` is `lch`,
 * `alpha(from <color> …)` is the space of the origin color.
 *
 * Not supported (a {@see ColorParseException} names the construct): `calc()` and other math
 * functions, `var()`, `contrast-color()`, `device-cmyk()`, custom color spaces (`@color-profile`),
 * CSS escape sequences. Colors that depend on a document (`currentcolor`, system colors,
 * `light-dark()`) raise {@see UnresolvableCssColorException}, also inside `color-mix()` or `from`.
 */
final readonly class CssParser
{
    /** CSS Color 4 § 6.2 (lowercase). */
    private const array SYSTEM_COLORS = [
        'accentcolor', 'accentcolortext', 'activetext', 'buttonborder', 'buttonface', 'buttontext',
        'canvas', 'canvastext', 'field', 'fieldtext', 'graytext', 'highlight', 'highlighttext',
        'linktext', 'mark', 'marktext', 'selecteditem', 'selecteditemtext', 'visitedtext',
    ];

    /** CSS Color 4 Appendix A (lowercase). */
    private const array DEPRECATED_SYSTEM_COLORS = [
        'activeborder', 'activecaption', 'appworkspace', 'background', 'buttonhighlight', 'buttonshadow',
        'captiontext', 'inactiveborder', 'inactivecaption', 'inactivecaptiontext', 'infobackground',
        'infotext', 'menu', 'menutext', 'scrollbar', 'threeddarkshadow', 'threedface', 'threedhighlight',
        'threedlightshadow', 'threedshadow', 'window', 'windowframe', 'windowtext',
    ];

    /** Functions that are valid CSS but outside this parser; named in the error message. */
    private const array UNSUPPORTED_FUNCTIONS = [
        'calc', 'min', 'max', 'clamp', 'var', 'env', 'attr', 'contrast-color', 'device-cmyk',
    ];

    /**
     * How the three components of each function are read.
     * - hue: <hue> (number in degrees or an angle)
     * - percent / number: value of 1 % / of 1 unit in the coordinates of the resulting space
     * - min / max: clamping at parsed-value time
     * - relative: the clamp also applies in relative color syntax (Lab/LCh/OKLab/OKLCh lightness and
     *   chroma, where values outside the range have no meaning; web-platform-tests expect it). Other
     *   components are not clamped in relative colors (CSS Color 5 § 4.1).
     *
     * @var array<string, list<array{hue: true}|array{percent: float, number: float, min?: float, max?: float, relative?: true}>>
     */
    private const array COMPONENTS = [
        'rgb' => [
            ['percent' => 0.01, 'number' => 1 / 255, 'min' => 0.0, 'max' => 1.0],
            ['percent' => 0.01, 'number' => 1 / 255, 'min' => 0.0, 'max' => 1.0],
            ['percent' => 0.01, 'number' => 1 / 255, 'min' => 0.0, 'max' => 1.0],
        ],
        'hsl' => [['hue' => true], ['percent' => 1.0, 'number' => 1.0, 'min' => 0.0], ['percent' => 1.0, 'number' => 1.0]],
        'hwb' => [['hue' => true], ['percent' => 1.0, 'number' => 1.0], ['percent' => 1.0, 'number' => 1.0]],
        'lab' => [['percent' => 1.0, 'number' => 1.0, 'min' => 0.0, 'max' => 100.0, 'relative' => true], ['percent' => 1.25, 'number' => 1.0], ['percent' => 1.25, 'number' => 1.0]],
        'lch' => [['percent' => 1.0, 'number' => 1.0, 'min' => 0.0, 'max' => 100.0, 'relative' => true], ['percent' => 1.5, 'number' => 1.0, 'min' => 0.0, 'relative' => true], ['hue' => true]],
        'oklab' => [['percent' => 0.01, 'number' => 1.0, 'min' => 0.0, 'max' => 1.0, 'relative' => true], ['percent' => 0.004, 'number' => 1.0], ['percent' => 0.004, 'number' => 1.0]],
        'oklch' => [['percent' => 0.01, 'number' => 1.0, 'min' => 0.0, 'max' => 1.0, 'relative' => true], ['percent' => 0.004, 'number' => 1.0, 'min' => 0.0, 'relative' => true], ['hue' => true]],
        'color' => [['percent' => 0.01, 'number' => 1.0], ['percent' => 0.01, 'number' => 1.0], ['percent' => 0.01, 'number' => 1.0]],
    ];

    /** Channel keywords of relative color syntax (CSS Color 5 § 4.3–4.9, § 5.1), per component. */
    private const array KEYWORDS = [
        'rgb' => ['r', 'g', 'b'],
        'hsl' => ['h', 's', 'l'],
        'hwb' => ['h', 'w', 'b'],
        'lab' => ['l', 'a', 'b'],
        'lch' => ['l', 'c', 'h'],
        'oklab' => ['l', 'a', 'b'],
        'oklch' => ['l', 'c', 'h'],
        'color' => ['r', 'g', 'b'],
    ];

    /** Functions that accept `from <color>`. */
    private const array RELATIVE_FUNCTIONS = ['rgb', 'rgba', 'hsl', 'hsla', 'hwb', 'lab', 'lch', 'oklab', 'oklch', 'color'];

    /** Degrees per unit of <angle> (CSS Values 4 § 7.1). */
    private const array ANGLE_UNITS = ['deg' => 1.0, 'grad' => 0.9, 'rad' => 180 / \M_PI, 'turn' => 360.0];

    /**
     * @throws ColorParseException
     * @throws UnresolvableCssColorException
     */
    public function parse(string $css): ColorValue
    {
        $tokens = CssTokenizer::tokenize($css);

        if ($tokens === []) {
            throw new ColorParseException($css, 0, 'a color', 'the input is empty');
        }

        $position = 0;
        $color = $this->color($css, $tokens, $position);
        self::expectEnd($css, $tokens, $position);

        return $color;
    }

    /**
     * Reads one `<color>` starting at $position and moves $position after it.
     *
     * @param list<CssToken> $tokens
     */
    private function color(string $css, array $tokens, int &$position): ColorValue
    {
        $first = $tokens[$position] ?? null;

        if ($first === null) {
            throw new ColorParseException($css, $tokens[$position - 1]->offset ?? \strlen($css), 'a color', 'the color is missing');
        }

        $position++;

        return match ($first->type) {
            CssTokenType::Hash => $this->hex($css, $first),
            CssTokenType::Ident => $this->keyword($css, $first),
            CssTokenType::Function => $this->function($css, $first, $tokens, $position),
            default => throw new ColorParseException($css, $first->offset, 'a hex color, a color keyword or a color function', 'unexpected token'),
        };
    }

    private function hex(string $css, CssToken $token): ColorValue
    {
        $rgba = HexDigits::decode($token->text);

        if ($rgba === null) {
            throw new ColorParseException($css, $token->offset, '"#" followed by 3, 4, 6 or 8 hexadecimal digits', \sprintf('"#%s" is not a hex color', $token->text));
        }

        return new ColorValue(ColorSpaces::srgb(), [$rgba[0], $rgba[1], $rgba[2]], $rgba[3]);
    }

    private function keyword(string $css, CssToken $token): ColorValue
    {
        $name = $token->lowerText();

        if ($name === 'transparent') {
            return new ColorValue(ColorSpaces::srgb(), [0.0, 0.0, 0.0], 0.0);
        }

        $rgb = CssNamedColors::get($name);
        if ($rgb !== null) {
            return new ColorValue(ColorSpaces::srgb(), [$rgb[0] / 255.0, $rgb[1] / 255.0, $rgb[2] / 255.0]);
        }

        if ($name === 'currentcolor' || \in_array($name, self::SYSTEM_COLORS, true) || \in_array($name, self::DEPRECATED_SYSTEM_COLORS, true)) {
            throw new UnresolvableCssColorException($css, $name);
        }

        throw new ColorParseException($css, $token->offset, 'a CSS named color, "transparent", a hex color or a color function', \sprintf('unknown color keyword "%s"', $token->text));
    }

    /**
     * @param list<CssToken> $tokens
     */
    private function function(string $css, CssToken $function, array $tokens, int &$position): ColorValue
    {
        $name = $function->lowerText();
        $depth = 0;
        $close = null;

        for ($index = $position, $count = \count($tokens); $index < $count; $index++) {
            if ($tokens[$index]->type === CssTokenType::Function) {
                $depth++;
            } elseif ($tokens[$index]->type === CssTokenType::CloseParen) {
                if ($depth === 0) {
                    $close = $index;
                    break;
                }
                $depth--;
            }
        }

        if ($close === null) {
            throw new ColorParseException($css, \strlen($css), '")"', 'the function is not closed');
        }

        $arguments = \array_slice($tokens, $position, $close - $position);
        $position = $close + 1;

        if ($name === 'light-dark') {
            throw new UnresolvableCssColorException($css, $name);
        }

        if ($name === 'color-mix') {
            return $this->colorMix($css, $function, $arguments);
        }

        $origin = null;
        if (($arguments[0] ?? null)?->type === CssTokenType::Ident && $arguments[0]->lowerText() === 'from') {
            if (!\in_array($name, [...self::RELATIVE_FUNCTIONS, 'alpha'], true)) {
                throw new ColorParseException($css, $arguments[0]->offset, 'a color function that accepts "from" (rgb(), hsl(), hwb(), lab(), lch(), oklab(), oklch(), color(), alpha())', \sprintf('"%s()" has no relative color syntax', $function->text));
            }

            $next = 1;
            $origin = $this->color($css, $arguments, $next);
            $arguments = \array_slice($arguments, $next);
        } elseif ($name === 'alpha') {
            throw new ColorParseException($css, $arguments[0]->offset ?? $function->offset, '"from" and an origin color', 'alpha() requires an origin color');
        }

        foreach ($arguments as $argument) {
            if ($argument->type === CssTokenType::Function) {
                throw new ColorParseException($css, $argument->offset, 'a number, a percentage, an angle, "none", "," or "/"', \sprintf('nested functions such as "%s()" (calc(), var()…) are not supported', $argument->text));
            }
        }

        return match ($name) {
            'rgb', 'rgba' => $origin === null
                ? $this->legacyOrModern($css, $function, 'rgb', $arguments, ColorSpaces::srgb())
                : $this->modern($css, $function, 'rgb', $arguments, ColorSpaces::srgb(), origin: $origin),
            'hsl', 'hsla' => $origin === null
                ? $this->legacyOrModern($css, $function, 'hsl', $arguments, ColorSpaces::hsl())
                : $this->modern($css, $function, 'hsl', $arguments, ColorSpaces::hsl(), origin: $origin),
            'hwb' => $this->modern($css, $function, 'hwb', $arguments, ColorSpaces::hwb(), origin: $origin),
            'lab' => $this->modern($css, $function, 'lab', $arguments, ColorSpaces::lab(), origin: $origin),
            'lch' => $this->modern($css, $function, 'lch', $arguments, ColorSpaces::lch(), origin: $origin),
            'oklab' => $this->modern($css, $function, 'oklab', $arguments, ColorSpaces::oklab(), origin: $origin),
            'oklch' => $this->modern($css, $function, 'oklch', $arguments, ColorSpaces::oklch(), origin: $origin),
            'color' => $this->colorFunction($css, $function, $arguments, $origin),
            'alpha' => $this->alphaFunction($css, $function, $arguments, $origin),
            default => throw new ColorParseException(
                $css,
                $function->offset,
                'rgb(), rgba(), hsl(), hsla(), hwb(), lab(), lch(), oklab(), oklch(), color(), color-mix() or alpha()',
                \in_array($name, self::UNSUPPORTED_FUNCTIONS, true)
                    ? \sprintf('the function "%s()" is not supported', $name)
                    : \sprintf('unknown color function "%s()"', $function->text),
            ),
        };
    }

    /**
     * @param list<CssToken> $arguments
     */
    private function legacyOrModern(string $css, CssToken $function, string $kind, array $arguments, ColorSpace $space): ColorValue
    {
        foreach ($arguments as $argument) {
            if ($argument->type === CssTokenType::Comma) {
                return $this->legacy($css, $function, $kind, $arguments, $space);
            }
        }

        return $this->modern($css, $function, $kind, $arguments, $space);
    }

    /**
     * Legacy (comma-separated) syntax, CSS Color 4 § 4.1.2: no `none`; rgb() components are all
     * numbers or all percentages; hsl() saturation and lightness are percentages.
     *
     * @param list<CssToken> $arguments
     */
    private function legacy(string $css, CssToken $function, string $kind, array $arguments, ColorSpace $space): ColorValue
    {
        /** @var list<CssToken> $values */
        $values = [];
        $expectValue = true;

        foreach ($arguments as $argument) {
            if ($expectValue === ($argument->type === CssTokenType::Comma)) {
                throw new ColorParseException($css, $argument->offset, $expectValue ? 'a value' : '","', 'the legacy (comma-separated) syntax separates every value by exactly one comma');
            }

            if (!$expectValue) {
                $expectValue = true;
                continue;
            }

            if ($argument->type === CssTokenType::Ident && $argument->lowerText() === 'none') {
                throw new ColorParseException($css, $argument->offset, 'a number or a percentage', '"none" is not allowed in the legacy (comma-separated) syntax');
            }

            $values[] = $argument;
            $expectValue = false;
        }

        $last = $arguments[\count($arguments) - 1] ?? null;
        if ($last !== null && $last->type === CssTokenType::Comma) {
            throw new ColorParseException($css, $last->offset, 'a value after ","', 'trailing comma');
        }

        if (\count($values) < 3 || \count($values) > 4) {
            throw new ColorParseException($css, $function->offset, \sprintf('%s() with 3 components and an optional alpha', $kind), \sprintf('%d value(s) given', \count($values)));
        }

        if ($kind === 'rgb' && ($values[1]->type !== $values[0]->type || $values[2]->type !== $values[0]->type)) {
            throw new ColorParseException($css, $values[0]->offset, 'three numbers or three percentages', 'the legacy rgb() syntax cannot mix numbers and percentages');
        }

        if ($kind === 'hsl') {
            foreach ([$values[1], $values[2]] as $value) {
                if ($value->type !== CssTokenType::Percentage) {
                    throw new ColorParseException($css, $value->offset, 'a percentage', 'the legacy hsl() syntax requires percentages for saturation and lightness');
                }
            }
        }

        $coordinates = [];
        foreach (self::COMPONENTS[$kind] as $index => $component) {
            $coordinates[] = $this->resolve($css, $values[$index], $component, null);
        }

        return new ColorValue($space, $coordinates, isset($values[3]) ? $this->alpha($css, $values[3]) : 1.0);
    }

    /**
     * Modern (space-separated) syntax, CSS Color 4 § 4.1.1: three components, optionally
     * "/ alpha"; `none` is allowed everywhere; numbers and percentages may be mixed.
     *
     * With an origin color (relative color syntax, CSS Color 5 § 4.1): the origin is converted to
     * $space carrying missing components forward; channel keywords ($keywords, `alpha`) stand for its
     * components as numbers (`none` when missing); components are not clamped; an omitted alpha is
     * the origin's alpha.
     *
     * @param list<CssToken> $arguments
     * @param list<string>|null $keywords channel keywords when they differ from {@see KEYWORDS} of $kind
     */
    private function modern(string $css, CssToken $function, string $kind, array $arguments, ColorSpace $space, string $label = '', ?ColorValue $origin = null, ?array $keywords = null): ColorValue
    {
        $label = $label === '' ? $kind . '()' : $label;
        [$components, $alpha] = $this->split($css, $function, $arguments, $label);
        $values = $origin === null ? null : $this->keywordValues($kind, $space, $origin, $keywords ?? self::KEYWORDS[$kind]);

        $coordinates = [];
        foreach (self::COMPONENTS[$kind] as $index => $component) {
            $coordinates[] = $this->resolve($css, $components[$index], $component, $values);
        }

        if ($alpha === null) {
            return new ColorValue($space, $coordinates, $origin === null ? 1.0 : $origin->alpha);
        }

        return new ColorValue($space, $coordinates, $this->resolveAlpha($css, $alpha, $values));
    }

    /**
     * Splits modern-syntax arguments into exactly three components and an optional alpha.
     *
     * @param list<CssToken> $arguments
     *
     * @return array{list<CssToken>, ?CssToken}
     */
    private function split(string $css, CssToken $function, array $arguments, string $label): array
    {
        $components = [];
        $alpha = null;
        $slash = null;

        foreach ($arguments as $argument) {
            if ($argument->type === CssTokenType::Comma) {
                throw new ColorParseException($css, $argument->offset, 'components separated by whitespace', \sprintf('commas are not allowed in %s', $label));
            }

            if ($argument->type === CssTokenType::Slash) {
                if ($slash !== null || \count($components) !== 3) {
                    throw new ColorParseException($css, $argument->offset, \sprintf('"/" after exactly 3 components of %s', $label), 'misplaced "/"');
                }
                $slash = $argument;
                continue;
            }

            if ($slash === null) {
                if (\count($components) === 3) {
                    throw new ColorParseException($css, $argument->offset, '"/" or ")"', \sprintf('%s takes 3 components', $label));
                }
                $components[] = $argument;
                continue;
            }

            if ($alpha !== null) {
                throw new ColorParseException($css, $argument->offset, '")"', 'only one alpha value is allowed');
            }
            $alpha = $argument;
        }

        if (\count($components) !== 3) {
            throw new ColorParseException($css, $function->offset, \sprintf('%s with 3 components', $label), \sprintf('%d component(s) given', \count($components)));
        }

        if ($slash !== null && $alpha === null) {
            throw new ColorParseException($css, $slash->offset, 'an alpha value after "/"', 'the alpha value is missing');
        }

        return [$components, $alpha];
    }

    /**
     * Values of the channel keywords of a relative color: the origin converted to $space, each
     * component as the number that would be written for it (e.g. `r` 0–255, `h` degrees in [0, 360)).
     *
     * @param list<string> $keywords
     *
     * @return array<string, float|null>
     */
    private function keywordValues(string $kind, ColorSpace $space, ColorValue $origin, array $keywords): array
    {
        $converted = MissingComponents::convert($origin, $space);
        $values = [];

        foreach (self::COMPONENTS[$kind] as $index => $component) {
            $value = $converted->coordinates[$index];

            $values[$keywords[$index]] = match (true) {
                $value === null => null,
                isset($component['hue']) => Angle::normalizeDegrees($value),
                default => $value / $component['number'],
            };
        }

        $values['alpha'] = $origin->alpha;

        return $values;
    }

    /**
     * @param array{hue: true}|array{percent: float, number: float, min?: float, max?: float, relative?: true} $component
     * @param array<string, float|null>|null $keywords keyword values of a relative color, null for an absolute one
     */
    private function resolve(string $css, CssToken $token, array $component, ?array $keywords): ?float
    {
        if (self::isNone($token)) {
            return null;
        }

        if ($keywords !== null && $token->type === CssTokenType::Ident && \array_key_exists($token->lowerText(), $keywords)) {
            $number = $keywords[$token->lowerText()];

            // A keyword is a <number> (CSS Color 5 § 4.2): degrees in a hue position, otherwise
            // scaled like a number written there.
            if ($number === null || isset($component['hue'])) {
                return $number;
            }

            return self::clamp($number * $component['number'], $component, true);
        }

        if (isset($component['hue'])) {
            return $this->hue($css, $token);
        }

        $value = match ($token->type) {
            CssTokenType::Number => $token->number * $component['number'],
            CssTokenType::Percentage => $token->number * $component['percent'],
            default => throw new ColorParseException($css, $token->offset, $keywords === null ? 'a number, a percentage or "none"' : 'a number, a percentage, a channel keyword or "none"', 'invalid component'),
        };

        return self::clamp($value, $component, $keywords !== null);
    }

    /**
     * @param array{percent: float, number: float, min?: float, max?: float, relative?: true} $component
     */
    private static function clamp(float $value, array $component, bool $relative): float
    {
        // Relative color components are not clamped (CSS Color 5 § 4.1), except where marked.
        if ($relative && !isset($component['relative'])) {
            return $value;
        }

        if (isset($component['min']) && $value < $component['min']) {
            return $component['min'];
        }

        if (isset($component['max']) && $value > $component['max']) {
            return $component['max'];
        }

        return $value;
    }

    /**
     * @param array<string, float|null>|null $keywords
     */
    private function resolveAlpha(string $css, CssToken $token, ?array $keywords): ?float
    {
        if (self::isNone($token)) {
            return null;
        }

        if ($keywords !== null && $token->type === CssTokenType::Ident && \array_key_exists($token->lowerText(), $keywords)) {
            $number = $keywords[$token->lowerText()];

            return $number === null ? null : \max(0.0, \min(1.0, $number));
        }

        return $this->alpha($css, $token);
    }

    /**
     * @param list<CssToken> $arguments
     */
    private function colorFunction(string $css, CssToken $function, array $arguments, ?ColorValue $origin): ColorValue
    {
        $name = $arguments[0] ?? null;

        if ($name === null || $name->type !== CssTokenType::Ident) {
            throw new ColorParseException($css, $name->offset ?? $function->offset, 'a predefined color space name', 'color() must start with a color space');
        }

        $space = match ($name->lowerText()) {
            'srgb' => ColorSpaces::srgb(),
            'srgb-linear' => ColorSpaces::srgbLinear(),
            'display-p3' => ColorSpaces::displayP3(),
            'display-p3-linear' => ColorSpaces::displayP3Linear(),
            'a98-rgb' => ColorSpaces::a98Rgb(),
            'prophoto-rgb' => ColorSpaces::prophotoRgb(),
            'rec2020' => ColorSpaces::rec2020(),
            'xyz', 'xyz-d65' => ColorSpaces::xyzD65(),
            'xyz-d50' => ColorSpaces::xyzD50(),
            default => throw new ColorParseException(
                $css,
                $name->offset,
                'srgb, srgb-linear, display-p3, display-p3-linear, a98-rgb, prophoto-rgb, rec2020, xyz, xyz-d50 or xyz-d65',
                \str_starts_with($name->text, '--')
                    ? 'custom color spaces (@color-profile) are not supported'
                    : \sprintf('unknown color space "%s"', $name->text),
            ),
        };

        $keywords = \str_starts_with($name->lowerText(), 'xyz') ? ['x', 'y', 'z'] : null;

        return $this->modern($css, $function, 'color', \array_slice($arguments, 1), $space, \sprintf('color(%s)', $name->lowerText()), $origin, $keywords);
    }

    /**
     * `alpha(from <color> / <alpha>)` (CSS Color 5 § 4.10): the origin color with another alpha,
     * in the origin's space. The only keyword is `alpha`.
     *
     * @param list<CssToken> $arguments
     */
    private function alphaFunction(string $css, CssToken $function, array $arguments, ?ColorValue $origin): ColorValue
    {
        \assert($origin !== null);

        if ($arguments === []) {
            return $origin;
        }

        if ($arguments[0]->type !== CssTokenType::Slash || \count($arguments) !== 2) {
            throw new ColorParseException($css, $arguments[0]->offset, '"/" and one alpha value, or ")"', 'alpha() takes only an alpha value');
        }

        return new ColorValue($origin->space, $origin->coordinates, $this->resolveAlpha($css, $arguments[1], ['alpha' => $origin->alpha]));
    }

    /**
     * `color-mix()` (CSS Color 5 § 3): `color-mix([in <space> [<hue-method> hue]] ,] [<color> && <percentage>?]#)`.
     *
     * @param list<CssToken> $arguments
     */
    private function colorMix(string $css, CssToken $function, array $arguments): ColorValue
    {
        $groups = self::commaGroups($arguments);
        $method = InterpolationMethod::oklab();

        if (($groups[0][0] ?? null)?->type === CssTokenType::Ident && $groups[0][0]->lowerText() === 'in') {
            $method = $this->interpolationMethod($css, \array_shift($groups));
        }

        $items = [];
        foreach ($groups as $group) {
            $items[] = $this->mixItem($css, $function, $group);
        }

        if ($items === []) {
            throw new ColorParseException($css, $function->offset, 'at least one color', 'color-mix() has no colors');
        }

        return ColorMix::mix($method, $items);
    }

    /**
     * `in <color-space> [<hue-interpolation-method> hue]?` (CSS Color 4 § 13.2).
     *
     * @param list<CssToken> $tokens starts with `in`
     */
    private function interpolationMethod(string $css, array $tokens): InterpolationMethod
    {
        $name = $tokens[1] ?? null;

        if ($name === null || $name->type !== CssTokenType::Ident) {
            throw new ColorParseException($css, $name->offset ?? $tokens[0]->offset, 'a color space after "in"', 'the interpolation color space is missing');
        }

        $space = match ($name->lowerText()) {
            'srgb' => ColorSpaces::srgb(),
            'srgb-linear' => ColorSpaces::srgbLinear(),
            'display-p3' => ColorSpaces::displayP3(),
            'display-p3-linear' => ColorSpaces::displayP3Linear(),
            'a98-rgb' => ColorSpaces::a98Rgb(),
            'prophoto-rgb' => ColorSpaces::prophotoRgb(),
            'rec2020' => ColorSpaces::rec2020(),
            'lab' => ColorSpaces::lab(),
            'oklab' => ColorSpaces::oklab(),
            'xyz', 'xyz-d65' => ColorSpaces::xyzD65(),
            'xyz-d50' => ColorSpaces::xyzD50(),
            'hsl' => ColorSpaces::hsl(),
            'hwb' => ColorSpaces::hwb(),
            'lch' => ColorSpaces::lch(),
            'oklch' => ColorSpaces::oklch(),
            default => throw new ColorParseException(
                $css,
                $name->offset,
                'srgb, srgb-linear, display-p3, display-p3-linear, a98-rgb, prophoto-rgb, rec2020, lab, oklab, xyz, xyz-d50, xyz-d65, hsl, hwb, lch or oklch',
                \str_starts_with($name->text, '--')
                    ? 'custom color spaces (@color-profile) are not supported'
                    : \sprintf('unknown interpolation color space "%s"', $name->text),
            ),
        };

        if (\count($tokens) === 2) {
            return new InterpolationMethod($space);
        }

        $polar = \in_array($name->lowerText(), ['hsl', 'hwb', 'lch', 'oklch'], true);
        $method = $tokens[2];
        $suffix = $tokens[3] ?? null;
        $hue = $method->type === CssTokenType::Ident ? HueInterpolation::tryFrom($method->lowerText()) : null;

        if (!$polar || $hue === null || \count($tokens) !== 4 || $suffix?->type !== CssTokenType::Ident || $suffix->lowerText() !== 'hue') {
            throw new ColorParseException(
                $css,
                $method->offset,
                $polar ? '"shorter hue", "longer hue", "increasing hue", "decreasing hue" or ","' : '","',
                $polar ? 'invalid hue interpolation method' : \sprintf('"%s" has no hue; a hue interpolation method is only allowed for hsl, hwb, lch and oklch', $name->lowerText()),
            );
        }

        return new InterpolationMethod($space, $hue);
    }

    /**
     * `<color> && <percentage [0,100]>?`
     *
     * @param list<CssToken> $tokens
     */
    private function mixItem(string $css, CssToken $function, array $tokens): MixItem
    {
        if ($tokens === []) {
            throw new ColorParseException($css, $function->offset, 'a color', 'empty argument in color-mix()');
        }

        $percentage = null;
        if ($tokens[0]->type === CssTokenType::Percentage) {
            $percentage = \array_shift($tokens);
        } elseif ($tokens[\count($tokens) - 1]->type === CssTokenType::Percentage) {
            $percentage = \array_pop($tokens);
        }

        if ($tokens === []) {
            \assert($percentage !== null);

            throw new ColorParseException($css, $percentage->offset, 'a color', 'the color is missing');
        }

        $position = 0;
        $color = $this->color($css, $tokens, $position);
        self::expectEnd($css, $tokens, $position);

        if ($percentage === null) {
            return new MixItem($color);
        }

        if ($percentage->number < 0.0 || $percentage->number > 100.0) {
            throw new ColorParseException($css, $percentage->offset, 'a percentage between 0% and 100%', 'mix percentages must be in the range 0% to 100%');
        }

        return new MixItem($color, $percentage->number / 100);
    }

    /**
     * Splits tokens at top-level commas (commas inside nested functions stay in their group).
     *
     * @param list<CssToken> $tokens
     *
     * @return list<list<CssToken>>
     */
    private static function commaGroups(array $tokens): array
    {
        $groups = [];
        $group = [];
        $depth = 0;

        foreach ($tokens as $token) {
            if ($token->type === CssTokenType::Comma && $depth === 0) {
                $groups[] = $group;
                $group = [];
                continue;
            }

            if ($token->type === CssTokenType::Function) {
                $depth++;
            } elseif ($token->type === CssTokenType::CloseParen) {
                $depth--;
            }

            $group[] = $token;
        }

        $groups[] = $group;

        return $groups;
    }

    private function hue(string $css, CssToken $token): float
    {
        if ($token->type === CssTokenType::Number) {
            return $token->number;
        }

        $degrees = $token->type === CssTokenType::Dimension ? (self::ANGLE_UNITS[$token->lowerText()] ?? null) : null;

        if ($degrees === null) {
            throw new ColorParseException($css, $token->offset, 'a hue: a number (degrees) or an angle in deg, grad, rad or turn', 'invalid hue');
        }

        return $token->number * $degrees;
    }

    /**
     * <alpha-value>: a number or a percentage, clamped to [0, 1] (CSS Color 4 § 4.2).
     */
    private function alpha(string $css, CssToken $token): float
    {
        $value = match ($token->type) {
            CssTokenType::Number => $token->number,
            CssTokenType::Percentage => $token->number / 100,
            default => throw new ColorParseException($css, $token->offset, 'an alpha value: a number or a percentage', 'invalid alpha'),
        };

        return \max(0.0, \min(1.0, $value));
    }

    private static function isNone(CssToken $token): bool
    {
        return $token->type === CssTokenType::Ident && $token->lowerText() === 'none';
    }

    /**
     * @param list<CssToken> $tokens
     */
    private static function expectEnd(string $css, array $tokens, int $count): void
    {
        if (isset($tokens[$count])) {
            throw new ColorParseException($css, $tokens[$count]->offset, 'the end of the color', 'unexpected content after the color');
        }
    }
}
