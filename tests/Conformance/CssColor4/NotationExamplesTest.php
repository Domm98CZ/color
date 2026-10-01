<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Conformance\CssColor4;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Notation\CssFormat;
use Domm98CZ\Color\Tests\Support\Fixture\FixtureLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The parsing and serialisation examples given in the text of CSS Color 4.
 */
final class NotationExamplesTest extends TestCase
{
    private const string FIXTURE = 'css-color-4/notation-examples.json';

    /**
     * @return iterable<string, array{string, ?string, ?string, int}>
     */
    public static function examples(): iterable
    {
        foreach (FixtureLoader::reference(self::FIXTURE)->cases as $case) {
            $input = $case['input'] ?? null;
            $same = $case['same_color_as'] ?? null;
            $serialization = $case['serialization'] ?? null;
            $digits = $case['fraction_digits'] ?? CssFormat::DEFAULT_FRACTION_DIGITS;
            \assert(\is_string($input) && ($same === null || \is_string($same)) && ($serialization === null || \is_string($serialization)) && \is_int($digits));
            $section = $case['section'] ?? '';
            \assert(\is_string($section));

            yield \sprintf('§%s %s', $section, $input) => [$input, $same, $serialization, $digits];
        }
    }

    #[DataProvider('examples')]
    public function testExample(string $input, ?string $sameColorAs, ?string $serialization, int $fractionDigits): void
    {
        $color = Color::parse($input);

        if ($sameColorAs !== null) {
            self::assertTrue(Color::parse($sameColorAs)->isCloseTo($color, FixtureLoader::reference(self::FIXTURE)->tolerance), \sprintf('%s is %s', $input, $sameColorAs));
        }

        if ($serialization !== null) {
            self::assertSame($serialization, $color->toCss(new CssFormat($fractionDigits)));
        }
    }
}
