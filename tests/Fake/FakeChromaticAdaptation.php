<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Fake;

use Domm98CZ\Color\Colorimetry\Contract\ChromaticAdaptationTransform;
use Domm98CZ\Color\Colorimetry\WhitePoint;
use Domm98CZ\Color\Math\Matrix3;
use Domm98CZ\Color\Provenance\AlgorithmId;

/**
 * Chromatic adaptation that returns a fixed matrix (identity by default, i.e. "XYZ scaling
 * without scaling") and records how it was called. Lets tests prove that ConversionOptions is
 * honoured and when adaptation happens.
 */
final class FakeChromaticAdaptation implements ChromaticAdaptationTransform
{
    /** @var list<array{string, string}> */
    public array $calls = [];

    private readonly Matrix3 $matrix;

    public function __construct(?Matrix3 $matrix = null)
    {
        $this->matrix = $matrix ?? Matrix3::identity();
    }

    public function id(): AlgorithmId
    {
        return new AlgorithmId('fake-adaptation');
    }

    public function matrix(WhitePoint $from, WhitePoint $to): Matrix3
    {
        $this->calls[] = [$from->name, $to->name];

        return $this->matrix;
    }
}
