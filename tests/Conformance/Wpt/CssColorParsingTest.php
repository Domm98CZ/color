<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Conformance\Wpt;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Notation\Exception\ColorParseException;
use Domm98CZ\Color\Notation\Exception\UnresolvableCssColorException;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Support\Fixture\FixtureLoader;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Parsing and serialisation against web-platform-tests css/css-color/parsing (frozen fixture,
 * extracted by tools/wpt/extract-css-color-parsing.php).
 *
 * - invalid cases must be rejected with ColorParseException;
 * - valid and computed cases are parsed with Color::parse(), serialised with toCss() and compared
 *   with the browser serialisation the way WPT's fuzzy comparison does: the same text around the
 *   numbers, every number within the fixture tolerance (see its meta for the 8-bit rgb() override);
 * - currentcolor and system colors must raise UnresolvableCssColorException.
 *
 * Cases outside the documented scope of the parser are excluded by {@see exclusionReason()} and
 * counted in {@see testExclusionsAreTheDocumentedOnes()}; nothing is skipped silently.
 */
final class CssColorParsingTest extends TestCase
{
    private const string FIXTURE = 'wpt/css-color-parsing.json';

    private const string NUMBER = '/(?<![a-z0-9-])-?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?/i';

    private const string UNSUPPORTED_FUNCTION = '/\b(?:calc|var|light-dark|contrast-color|device-cmyk|min|max|clamp|sign|abs|round|mod|rem|sin|cos|tan|asin|acos|atan|atan2|exp|pow|sqrt|hypot|log|attr|env)\(/i';

    private const array SYSTEM_COLORS = ['currentcolor', 'canvas', 'canvastext', 'buttonface', 'buttontext', 'linktext', 'visitedtext', 'activetext', 'field', 'fieldtext', 'highlight', 'highlighttext', 'graytext', 'mark', 'marktext', 'accentcolor', 'accentcolortext', 'buttonborder', 'selecteditem', 'selecteditemtext'];

    /**
     * Known limitations: unsupported functions (calc() and other math
     * functions, var(), light-dark(), contrast-color(), device-cmyk()…) and CSS escape sequences.
     */
    private static function exclusionReason(string $input, ?string $expected): ?string
    {
        if (\preg_match(self::UNSUPPORTED_FUNCTION, $input) === 1 || ($expected !== null && \preg_match(self::UNSUPPORTED_FUNCTION, $expected) === 1)) {
            return 'unsupported function';
        }

        return \str_contains($input, '\\') ? 'CSS escape sequence' : null;
    }

    /**
     * @return iterable<string, array{string, string, ?string}>
     */
    public static function cases(): iterable
    {
        foreach (FixtureLoader::reference(self::FIXTURE)->cases as $index => $case) {
            [$kind, $input, $expected] = self::fields($case);

            if (self::exclusionReason($input, $expected) === null) {
                yield \sprintf('#%d %s %s', $index, $kind, $input) => [$kind, $input, $expected];
            }
        }
    }

    #[DataProvider('cases')]
    public function testCase(string $kind, string $input, ?string $expected): void
    {
        if ($kind === 'invalid' || $expected === null) {
            $this->expectException(ColorParseException::class);
            Color::parse($input);

            return;
        }

        // The computed value of currentcolor comes from the document (WPT sets `color` on the element),
        // also inside color-mix() or as the origin of a relative color.
        if (\preg_match('/\b(?:' . \implode('|', self::SYSTEM_COLORS) . ')\b/i', $input . ' ' . $expected) === 1) {
            $this->expectException(UnresolvableCssColorException::class);
            Color::parse($input);

            return;
        }

        $color = Color::parse($input);
        $actual = $color->toCss();

        // The specified value of color-mix() and of a relative color is the function itself
        // (normalised); this library resolves it. Compared as the colors both strings describe.
        // Browsers write legacy sRGB arguments at 8 bits there, hence the 8-bit tolerance in sRGB.
        if ($kind === 'valid' && \preg_match('/color-mix\(|\bfrom\b/i', $expected) === 1) {
            $tolerance = \preg_match('/rgba?\(\d/', $expected) === 1 ? Tolerance::absolute(Tolerances::SRGB_EIGHT_BIT_STEP) : $this->tolerance();
            self::assertTrue(Color::parse($expected)->to(ColorSpaces::srgb())->isCloseTo($color->to(ColorSpaces::srgb()), $tolerance), \sprintf('%s: %s vs %s', $input, $actual, $expected));

            return;
        }

        // A bare keyword (declared value of a named color, "transparent") is compared as the color it names.
        if (\preg_match('/^[a-z]+$/i', $expected) === 1) {
            $expected = Color::parse($expected)->toCss();
        }

        // Documented deviation: a color in `srgb` is written as rgb(), also when it was written as
        // color(srgb …) (the Color does not remember the notation). Compared as colors instead.
        if (\str_starts_with($expected, 'color(srgb ') && \str_starts_with($actual, 'rgb')) {
            self::assertTrue(Color::parse($expected)->isCloseTo($color, $this->tolerance()), \sprintf('%s: %s vs %s', $input, $actual, $expected));

            return;
        }

        $this->assertSerializationMatches($input, $actual, $expected);
    }

    public function testExclusionsAreTheDocumentedOnes(): void
    {
        $excluded = [];
        foreach (FixtureLoader::reference(self::FIXTURE)->cases as $case) {
            [, $input, $expected] = self::fields($case);
            $reason = self::exclusionReason($input, $expected);
            if ($reason !== null) {
                $excluded[$reason] = ($excluded[$reason] ?? 0) + 1;
            }
        }

        \ksort($excluded);
        self::assertSame(['CSS escape sequence' => 2, 'unsupported function' => 498], $excluded);
    }

    private function assertSerializationMatches(string $input, string $actual, string $expected): void
    {
        $message = \sprintf('%s: ours "%s", expected "%s"', $input, $actual, $expected);
        self::assertSame(\preg_replace(self::NUMBER, '#', $expected), \preg_replace(self::NUMBER, '#', $actual), $message);

        \preg_match_all(self::NUMBER, $actual, $ours);
        \preg_match_all(self::NUMBER, $expected, $theirs);
        $legacyRgb = \str_starts_with($expected, 'rgb');

        foreach ($theirs[0] as $index => $number) {
            $tolerance = $legacyRgb && $index < 3 ? $this->legacyRgbTolerance() : $this->tolerance()->absolute;
            self::assertEqualsWithDelta((float) $number, (float) $ours[0][$index], $tolerance + 1e-9, $message);
        }
    }

    private function tolerance(): Tolerance
    {
        return FixtureLoader::reference(self::FIXTURE)->tolerance;
    }

    private function legacyRgbTolerance(): float
    {
        $overrides = FixtureLoader::reference(self::FIXTURE)->meta['tolerance_overrides'] ?? null;
        \assert(\is_array($overrides) && \is_array($overrides['legacy-rgb-channels'] ?? null));

        return FixtureLoader::number($overrides['legacy-rgb-channels']['abs'] ?? null);
    }

    /**
     * @param array<string, mixed> $case
     *
     * @return array{string, string, ?string}
     */
    private static function fields(array $case): array
    {
        $kind = $case['kind'] ?? null;
        $input = $case['input'] ?? null;
        $expected = $case['expected'] ?? null;
        \assert(\is_string($kind) && \is_string($input) && ($expected === null || \is_string($expected)));

        return [$kind, $input, $expected];
    }
}
