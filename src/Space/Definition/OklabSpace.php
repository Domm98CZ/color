<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Definition;

use Domm98CZ\Color\Colorimetry\WhitePoint;
use Domm98CZ\Color\Math\Matrix3;
use Domm98CZ\Color\Math\Numeric;
use Domm98CZ\Color\Math\Vector3;
use Domm98CZ\Color\Space\Channel;
use Domm98CZ\Color\Space\ChannelKind;
use Domm98CZ\Color\Space\ComponentCategory;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Internal\SpaceIdentifier;

/**
 * OKLab (B. Ottosson 2020) relative to D65, with the matrices recalculated for a consistent D65 white
 * and 64-bit precision as published in CSS Color 4 (CRD 2026-09-26, XYZ_to_OKLab / OKLab_to_XYZ;
 * w3c/csswg-drafts#6642, color-js/color.js#357).
 */
final readonly class OklabSpace implements ColorSpace
{
    private const array XYZ_TO_LMS = [
        [0.8190224379967030, 0.3619062600528904, -0.1288737815209879],
        [0.0329836539323885, 0.9292868615863434, 0.0361446663506424],
        [0.0481771893596242, 0.2642395317527308, 0.6335478284694309],
    ];

    private const array LMS_TO_OKLAB = [
        [0.2104542683093140, 0.7936177747023054, -0.0040720430116193],
        [1.9779985324311684, -2.4285922420485799, 0.4505937096174110],
        [0.0259040424655478, 0.7827717124575296, -0.8086757549230774],
    ];

    private const array LMS_TO_XYZ = [
        [1.2268798758459243, -0.5578149944602171, 0.2813910456659647],
        [-0.0405757452148008, 1.1122868032803170, -0.0717110580655164],
        [-0.0763729366746601, -0.4214933324022432, 1.5869240198367816],
    ];

    private const array OKLAB_TO_LMS = [
        [1.0000000000000000, 0.3963377773761749, 0.2158037573099136],
        [1.0000000000000000, -0.1055613458156586, -0.0638541728258133],
        [1.0000000000000000, -0.0894841775298119, -1.2914855480194092],
    ];

    /** @var non-empty-string */
    private string $id;

    private Matrix3 $xyzToLms;
    private Matrix3 $lmsToOklab;
    private Matrix3 $lmsToXyz;
    private Matrix3 $oklabToLms;

    /**
     * @param XyzSpace $xyz must be relative to D65 (the matrices assume it)
     */
    public function __construct(private XyzSpace $xyz, string $id = 'oklab')
    {
        $this->id = SpaceIdentifier::id($id);
        $this->xyzToLms = Matrix3::fromRows(self::XYZ_TO_LMS);
        $this->lmsToOklab = Matrix3::fromRows(self::LMS_TO_OKLAB);
        $this->lmsToXyz = Matrix3::fromRows(self::LMS_TO_XYZ);
        $this->oklabToLms = Matrix3::fromRows(self::OKLAB_TO_LMS);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return 'OKLab';
    }

    public function channels(): array
    {
        return [
            new Channel('l', 'Lightness', ChannelKind::Number, 0.0, 1.0, ComponentCategory::Lightness),
            new Channel('a', 'a', ChannelKind::Number, -0.4, 0.4, ComponentCategory::OpponentA),
            new Channel('b', 'b', ChannelKind::Number, -0.4, 0.4, ComponentCategory::OpponentB),
        ];
    }

    public function base(): XyzSpace
    {
        return $this->xyz;
    }

    public function toBase(array $coordinates): array
    {
        $lms = $this->oklabToLms->transform(Vector3::fromList($coordinates));

        return $this->lmsToXyz->transform(new Vector3($lms->x ** 3, $lms->y ** 3, $lms->z ** 3))->toList();
    }

    public function fromBase(array $coordinates): array
    {
        $lms = $this->xyzToLms->transform(Vector3::fromList($coordinates));

        return $this->lmsToOklab->transform(new Vector3(Numeric::cbrt($lms->x), Numeric::cbrt($lms->y), Numeric::cbrt($lms->z)))->toList();
    }

    public function whitePoint(): WhitePoint
    {
        return $this->xyz->whitePoint();
    }

    public function gamutSpace(): ?ColorSpace
    {
        return null;
    }
}
