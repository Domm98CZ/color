<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Conformance\Ottosson;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Math\Vector3;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Tests\Support\Fixture\FixtureLoader;
use Domm98CZ\Color\Tests\Support\FloatAssertions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * XYZ → OKLab reproduces the example table published by the author of OKLab.
 */
final class OklabExamplesTest extends TestCase
{
    use FloatAssertions;

    private const string FIXTURE = 'ottosson-2020/oklab-examples.json';

    /**
     * @return iterable<string, array{Vector3, Vector3}>
     */
    public static function examples(): iterable
    {
        foreach (FixtureLoader::reference(self::FIXTURE)->cases as $case) {
            $xyz = Vector3::fromList(\array_map(FixtureLoader::number(...), (array) ($case['xyz'] ?? [])));
            $oklab = Vector3::fromList(\array_map(FixtureLoader::number(...), (array) ($case['oklab'] ?? [])));

            yield \json_encode($xyz->toList(), \JSON_THROW_ON_ERROR) => [$xyz, $oklab];
        }
    }

    #[DataProvider('examples')]
    public function testXyzToOklab(Vector3 $xyz, Vector3 $expected): void
    {
        $actual = Vector3::fromList(\array_map(
            static fn(?float $v): float => $v ?? 0.0,
            Color::of(ColorSpaces::xyzD65(), $xyz->toList())->to(ColorSpaces::oklab())->coordinates(),
        ));

        self::assertVectorClose($expected, $actual, FixtureLoader::reference(self::FIXTURE)->tolerance);
    }
}
