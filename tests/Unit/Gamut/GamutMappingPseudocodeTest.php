<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Gamut;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Gamut\Contract\GamutMapper;
use Domm98CZ\Color\Gamut\GamutMappers;
use Domm98CZ\Color\Math\Tolerance;
use Domm98CZ\Color\Space\ColorSpaceRegistry;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use Domm98CZ\Color\Tests\Support\Tolerances;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The CSS Color 4 gamut mapping pseudocode (§ 14.2.2 binary search with local MINDE, § 14.2.6 ray
 * trace) and chroma reduction, step for step: expected values come from an independent
 * re-implementation of the pseudocode (Python with the Coloraide 8.13 conversions, 2026-09-28; not
 * kept in the repository), which agrees with the library to 6.5e-15. They pin every step of the
 * algorithms (loop bounds, the anchor update of the ray trace, the local-MINDE exit), which the
 * examples of the specification (printed with 3 decimals) cannot.
 */
final class GamutMappingPseudocodeTest extends TestCase
{
    use FloatAssertions;

    /**
     * @return iterable<string, array{string, string, list<float>, string, list<float>}>
     */
    public static function cases(): iterable
    {
        // The only input of a 5 × 3 × 6 grid whose bisection ends by max − min ≤ ε instead of the local-MINDE exit.
        yield 'oklch 0.3 0.4 0.0 → srgb (css, ends by epsilon)' => ['css', 'oklch', [0.3, 0.4, 0.0], 'srgb', [0.369331949272802, 0.0, 0.16818348804717573]];
        yield 'oklch 0.7 0.3 30.0 → srgb (css)' => ['css', 'oklch', [0.7, 0.3, 30.0], 'srgb', [1.0, 0.34513507874832117, 0.2645751122517649]];
        yield 'oklch 0.7 0.3 30.0 → srgb (ray)' => ['ray', 'oklch', [0.7, 0.3, 30.0], 'srgb', [0.9999999999999999, 0.3962424693569543, 0.3182147311450287]];
        yield 'oklch 0.7 0.3 30.0 → srgb (chroma)' => ['chroma', 'oklch', [0.7, 0.3, 30.0], 'srgb', [0.9999040547395551, 0.39634735211956873, 0.3183281277646404]];
        yield 'oklch 0.7 0.3 30.0 → display-p3 (css)' => ['css', 'oklch', [0.7, 0.3, 30.0], 'display-p3', [1.0, 0.28509550961159424, 0.19277696636614466]];
        yield 'oklch 0.7 0.3 30.0 → display-p3 (ray)' => ['ray', 'oklch', [0.7, 0.3, 30.0], 'display-p3', [0.9999999999999999, 0.3497557121545721, 0.25841304709928675]];
        yield 'oklch 0.7 0.3 30.0 → display-p3 (chroma)' => ['chroma', 'oklch', [0.7, 0.3, 30.0], 'display-p3', [0.999957990636151, 0.34981721520640885, 0.25847683787434583]];
        yield 'oklch 0.5 0.25 150.0 → srgb (css)' => ['css', 'oklch', [0.5, 0.25, 150.0], 'srgb', [0.0, 0.4847818465887885, 0.14611487843524051]];
        yield 'oklch 0.5 0.25 150.0 → srgb (ray)' => ['ray', 'oklch', [0.5, 0.25, 150.0], 'srgb', [0.0, 0.46990000699635065, 0.20708505584564713]];
        yield 'oklch 0.5 0.25 150.0 → srgb (chroma)' => ['chroma', 'oklch', [0.5, 0.25, 150.0], 'srgb', [3.600724715251011e-05, 0.4698984033052183, 0.20709061211335428]];
        yield 'oklch 0.5 0.25 150.0 → display-p3 (css)' => ['css', 'oklch', [0.5, 0.25, 150.0], 'display-p3', [0.0, 0.49855337418844553, 0.08406680088319393]];
        yield 'oklch 0.5 0.25 150.0 → display-p3 (ray)' => ['ray', 'oklch', [0.5, 0.25, 150.0], 'display-p3', [5.603156827405087e-18, 0.48560934988429755, 0.15667460661447813]];
        yield 'oklch 0.5 0.25 150.0 → display-p3 (chroma)' => ['chroma', 'oklch', [0.5, 0.25, 150.0], 'display-p3', [0.00029880164289601395, 0.48559386408866106, 0.15674240768772216]];
        yield 'oklch 0.9 0.25 100.0 → srgb (css)' => ['css', 'oklch', [0.9, 0.25, 100.0], 'srgb', [0.9989544683606567, 0.8734752932538052, 0.0]];
        yield 'oklch 0.9 0.25 100.0 → srgb (ray)' => ['ray', 'oklch', [0.9, 0.25, 100.0], 'srgb', [0.9863881516983463, 0.8754601255539064, 0.0]];
        yield 'oklch 0.9 0.25 100.0 → srgb (chroma)' => ['chroma', 'oklch', [0.9, 0.25, 100.0], 'srgb', [0.9863640663751713, 0.875463530324916, 0.001305454981104942]];
        yield 'oklch 0.9 0.25 100.0 → display-p3 (css)' => ['css', 'oklch', [0.9, 0.25, 100.0], 'display-p3', [0.9942952174901499, 0.875058096863653, 0.0]];
        yield 'oklch 0.9 0.25 100.0 → display-p3 (ray)' => ['ray', 'oklch', [0.9, 0.25, 100.0], 'display-p3', [0.9840093284310051, 0.8770614180605414, 0.0]];
        yield 'oklch 0.9 0.25 100.0 → display-p3 (chroma)' => ['chroma', 'oklch', [0.9, 0.25, 100.0], 'display-p3', [0.9839798248098571, 0.8770666336576888, 0.0015599592916048254]];
        yield 'oklch 0.3 0.2 270.0 → srgb (css)' => ['css', 'oklch', [0.3, 0.2, 270.0], 'srgb', [0.08653199513637957, 0.0, 0.5616112808506335]];
        yield 'oklch 0.3 0.2 270.0 → srgb (ray)' => ['ray', 'oklch', [0.3, 0.2, 270.0], 'srgb', [0.08553008225182271, 0.0, 0.5551408644195474]];
        yield 'oklch 0.3 0.2 270.0 → srgb (chroma)' => ['chroma', 'oklch', [0.3, 0.2, 270.0], 'srgb', [0.08552188789123011, 0.0001153639116682218, 0.5550845116597243]];
        yield 'oklch 0.3 0.2 270.0 → display-p3 (css)' => ['css', 'oklch', [0.3, 0.2, 270.0], 'display-p3', [0.0739415521625918, 0.0, 0.538053556752244]];
        yield 'oklch 0.3 0.2 270.0 → display-p3 (ray)' => ['ray', 'oklch', [0.3, 0.2, 270.0], 'display-p3', [0.07437203916293345, 0.0, 0.5335381692514821]];
        yield 'oklch 0.3 0.2 270.0 → display-p3 (chroma)' => ['chroma', 'oklch', [0.3, 0.2, 270.0], 'display-p3', [0.07438138579110934, 0.00019382569145896865, 0.5334452080816837]];
        yield 'oklch 0.95 0.1 200.0 → srgb (css)' => ['css', 'oklch', [0.95, 0.1, 200.0], 'srgb', [0.5948319217961673, 1.0, 1.0]];
        yield 'oklch 0.95 0.1 200.0 → srgb (ray)' => ['ray', 'oklch', [0.95, 0.1, 200.0], 'srgb', [0.7434645131525254, 0.9878167837409103, 0.9999999999999999]];
        yield 'oklch 0.95 0.1 200.0 → srgb (chroma)' => ['chroma', 'oklch', [0.95, 0.1, 200.0], 'srgb', [0.7435388756629592, 0.9877997628639522, 0.9999778451633868]];
        yield 'oklch 0.95 0.1 200.0 → display-p3 (css)' => ['css', 'oklch', [0.95, 0.1, 200.0], 'display-p3', [0.695417627369019, 1.0, 1.0]];
        yield 'oklch 0.95 0.1 200.0 → display-p3 (ray)' => ['ray', 'oklch', [0.95, 0.1, 200.0], 'display-p3', [0.7826570049389894, 0.9842299215328024, 0.9999999999999999]];
        yield 'oklch 0.95 0.1 200.0 → display-p3 (chroma)' => ['chroma', 'oklch', [0.95, 0.1, 200.0], 'display-p3', [0.7827005760070675, 0.9842177590742159, 0.9999830001114974]];
        yield 'oklch 0.2 0.3 330.0 → srgb (css)' => ['css', 'oklch', [0.2, 0.3, 330.0], 'srgb', [0.18203797453779147, 0.0, 0.17683339047398208]];
        yield 'oklch 0.2 0.3 330.0 → srgb (ray)' => ['ray', 'oklch', [0.2, 0.3, 330.0], 'srgb', [0.1664472563800131, 0.0, 0.16022234267475824]];
        yield 'oklch 0.2 0.3 330.0 → srgb (chroma)' => ['chroma', 'oklch', [0.2, 0.3, 330.0], 'srgb', [0.16641064709881, 6.318268733421003e-05, 0.16018407029769605]];
        yield 'oklch 0.2 0.3 330.0 → display-p3 (css)' => ['css', 'oklch', [0.2, 0.3, 330.0], 'display-p3', [0.1673627646646864, 0.0, 0.17587989894088088]];
        yield 'oklch 0.2 0.3 330.0 → display-p3 (ray)' => ['ray', 'oklch', [0.2, 0.3, 330.0], 'display-p3', [0.15433222993091678, 0.0, 0.1599216807948258]];
        yield 'oklch 0.2 0.3 330.0 → display-p3 (chroma)' => ['chroma', 'oklch', [0.2, 0.3, 330.0], 'display-p3', [0.15430579614406464, 5.2417978760846334e-05, 0.15988984228010467]];
        yield 'display-p3 1.0 0.5 0.0 → srgb (css)' => ['css', 'display-p3', [1.0, 0.5, 0.0], 'srgb', [1.0, 0.49047859686165035, 0.0]];
        yield 'display-p3 1.0 0.5 0.0 → srgb (ray)' => ['ray', 'display-p3', [1.0, 0.5, 0.0], 'srgb', [0.9999999999999999, 0.5249267713989112, 0.18634003735856838]];
        yield 'display-p3 1.0 0.5 0.0 → srgb (chroma)' => ['chroma', 'display-p3', [1.0, 0.5, 0.0], 'srgb', [0.999958361773435, 0.5249571665731659, 0.18647866778798036]];
        yield 'display-p3 1.0 0.5 0.0 → display-p3 (css)' => ['css', 'display-p3', [1.0, 0.5, 0.0], 'display-p3', [1.0, 0.5, 0.0]];
        yield 'display-p3 1.0 0.5 0.0 → display-p3 (ray)' => ['ray', 'display-p3', [1.0, 0.5, 0.0], 'display-p3', [1.0, 0.5, 0.0]];
        yield 'display-p3 1.0 0.5 0.0 → display-p3 (chroma)' => ['chroma', 'display-p3', [1.0, 0.5, 0.0], 'display-p3', [1.0, 0.5, 0.0]];
        yield 'rec2020 0.0 1.0 0.0 → srgb (css)' => ['css', 'rec2020', [0.0, 1.0, 0.0], 'srgb', [0.0, 0.9473440908897227, 0.4462805435023001]];
        yield 'rec2020 0.0 1.0 0.0 → srgb (ray)' => ['ray', 'rec2020', [0.0, 1.0, 0.0], 'srgb', [0.0, 0.9304314546460296, 0.49546324312800843]];
        yield 'rec2020 0.0 1.0 0.0 → srgb (chroma)' => ['chroma', 'rec2020', [0.0, 1.0, 0.0], 'srgb', [0.0005986510861895977, 0.9304206010449978, 0.49549282633943464]];
        yield 'rec2020 0.0 1.0 0.0 → display-p3 (css)' => ['css', 'rec2020', [0.0, 1.0, 0.0], 'display-p3', [0.0, 0.9740718107126566, 0.3744480224386012]];
        yield 'rec2020 0.0 1.0 0.0 → display-p3 (ray)' => ['ray', 'rec2020', [0.0, 1.0, 0.0], 'display-p3', [2.2412627309620348e-17, 0.9592211257334123, 0.42472850493312403]];
        yield 'rec2020 0.0 1.0 0.0 → display-p3 (chroma)' => ['chroma', 'rec2020', [0.0, 1.0, 0.0], 'display-p3', [3.259404809568656e-05, 0.95922043505794, 0.4247306830253606]];
    }

    /**
     * @param list<float> $coordinates
     * @param list<float> $expected
     */
    #[DataProvider('cases')]
    public function testMatchesThePseudocode(string $mapper, string $space, array $coordinates, string $destination, array $expected): void
    {
        $registry = ColorSpaceRegistry::default();
        $result = self::mapper($mapper)->map(Color::of($registry->get($space), $coordinates), $registry->get($destination))->color;

        foreach ($expected as $index => $value) {
            self::assertFloatClose($value, $result->coordinates()[$index] ?? \NAN, Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB), (string) $index);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mappers(): iterable
    {
        yield 'css' => ['css'];
        yield 'ray' => ['ray'];
        yield 'chroma' => ['chroma'];
    }

    #[DataProvider('mappers')]
    public function testLightnessExtremesAreExactlyWhiteAndBlack(string $mapper): void
    {
        $map = self::mapper($mapper);

        self::assertSame('#ffffff', $map->map(Color::fromOklch(1.0, 0.2, 120.0), ColorSpaces::srgb())->color->toHex());
        self::assertSame([1.0, 1.0, 1.0], \array_map(static fn(?float $v): float => \round($v ?? \NAN, 12), $map->map(Color::fromOklch(1.0, 0.2, 120.0), ColorSpaces::srgb())->color->coordinates()));
        self::assertSame([0.0, 0.0, 0.0], \array_map(static fn(?float $v): float => \round($v ?? \NAN, 12), $map->map(Color::fromOklch(0.0, 0.2, 120.0), ColorSpaces::srgb())->color->coordinates()));
        self::assertSame([0.0, 0.0, 0.0], \array_map(static fn(?float $v): float => \round($v ?? \NAN, 12), $map->map(Color::of(ColorSpaces::oklch(), [null, 0.3, 120.0]), ColorSpaces::srgb())->color->coordinates()), 'a missing lightness is 0');
    }

    #[DataProvider('mappers')]
    public function testADestinationWithoutGamutOnlyConverts(string $mapper): void
    {
        $lab = Color::fromLab(50.0, 200.0, -150.0);
        $result = self::mapper($mapper)->map($lab, ColorSpaces::lab());

        self::assertSame([50.0, 200.0, -150.0], $result->color->coordinates());
        self::assertTrue($result->wasInGamut);
    }

    public function testClipClampsInTheGamutSpace(): void
    {
        // For HSL the gamut is sRGB: clamp (1.2, 0.5, 0.2) to (1, 0.5, 0.2), then convert to HSL.
        $result = GamutMappers::clip()->map(Color::fromSrgb(1.2, 0.5, 0.2), ColorSpaces::hsl())->color;
        $expected = Color::fromSrgb(1.0, 0.5, 0.2)->to(ColorSpaces::hsl());

        self::assertTrue($result->isCloseTo($expected, Tolerance::absolute(Tolerances::CONVERSION_ROUND_TRIP_SRGB)));
    }

    private static function mapper(string $name): GamutMapper
    {
        return match ($name) {
            'css' => GamutMappers::cssColor4(),
            'ray' => GamutMappers::rayTrace(),
            default => GamutMappers::chromaReduction(),
        };
    }
}
