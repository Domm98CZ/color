<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space;

use Domm98CZ\Color\Colorimetry\Algorithm\GammaTransferFunction;
use Domm98CZ\Color\Colorimetry\Algorithm\ProPhotoTransferFunction;
use Domm98CZ\Color\Colorimetry\Algorithm\SrgbTransferFunction;
use Domm98CZ\Color\Colorimetry\Chromaticity;
use Domm98CZ\Color\Colorimetry\RgbPrimaries;
use Domm98CZ\Color\Colorimetry\WhitePoints;
use Domm98CZ\Color\Space\Contract\ColorSpace;
use Domm98CZ\Color\Space\Definition\HslSpace;
use Domm98CZ\Color\Space\Definition\HsvSpace;
use Domm98CZ\Color\Space\Definition\HwbSpace;
use Domm98CZ\Color\Space\Definition\LabSpace;
use Domm98CZ\Color\Space\Definition\LinearRgbSpace;
use Domm98CZ\Color\Space\Definition\OklabSpace;
use Domm98CZ\Color\Space\Definition\PolarSpace;
use Domm98CZ\Color\Space\Definition\RgbSpace;
use Domm98CZ\Color\Space\Definition\XyzSpace;

/**
 * Built-in color spaces. Every method returns the same immutable instance on each call.
 *
 * Definitions follow CSS Color 4 (CRD 2026-09-26) unless stated otherwise.
 */
final class ColorSpaces
{
    /** @var array<string, ColorSpace> */
    private static array $instances = [];

    private function __construct() {}

    public static function xyzD65(): XyzSpace
    {
        return self::memo('xyz-d65', static fn(): XyzSpace => new XyzSpace('xyz-d65', 'CIE XYZ (D65)', WhitePoints::d65()));
    }

    public static function xyzD50(): XyzSpace
    {
        return self::memo('xyz-d50', static fn(): XyzSpace => new XyzSpace('xyz-d50', 'CIE XYZ (D50)', WhitePoints::d50()));
    }

    /** sRGB, IEC 61966-2-1 primaries and transfer function, D65. */
    public static function srgb(): RgbSpace
    {
        return self::memo('srgb', static fn(): RgbSpace => new RgbSpace('srgb', 'sRGB', new SrgbTransferFunction(), self::srgbLinear()));
    }

    public static function srgbLinear(): LinearRgbSpace
    {
        return self::memo('srgb-linear', static fn(): LinearRgbSpace => new LinearRgbSpace(
            'srgb-linear',
            'sRGB (linear)',
            new RgbPrimaries(new Chromaticity(0.64, 0.33), new Chromaticity(0.30, 0.60), new Chromaticity(0.15, 0.06)),
            self::xyzD65(),
        ));
    }

    /** Display P3: DCI-P3 primaries (SMPTE EG 432-1), D65 white, sRGB transfer function. */
    public static function displayP3(): RgbSpace
    {
        return self::memo('display-p3', static fn(): RgbSpace => new RgbSpace('display-p3', 'Display P3', new SrgbTransferFunction(), self::displayP3Linear()));
    }

    public static function displayP3Linear(): LinearRgbSpace
    {
        return self::memo('display-p3-linear', static fn(): LinearRgbSpace => new LinearRgbSpace(
            'display-p3-linear',
            'Display P3 (linear)',
            new RgbPrimaries(new Chromaticity(0.680, 0.320), new Chromaticity(0.265, 0.690), new Chromaticity(0.150, 0.060)),
            self::xyzD65(),
        ));
    }

    /** Adobe RGB (1998) compatible, CSS `a98-rgb`: gamma 563/256, D65. */
    public static function a98Rgb(): RgbSpace
    {
        return self::memo('a98-rgb', static fn(): RgbSpace => new RgbSpace('a98-rgb', 'A98 RGB (Adobe RGB 1998 compatible)', new GammaTransferFunction(563 / 256), self::a98RgbLinear()));
    }

    public static function a98RgbLinear(): LinearRgbSpace
    {
        return self::memo('a98-rgb-linear', static fn(): LinearRgbSpace => new LinearRgbSpace(
            'a98-rgb-linear',
            'A98 RGB (linear)',
            new RgbPrimaries(new Chromaticity(0.64, 0.33), new Chromaticity(0.21, 0.71), new Chromaticity(0.15, 0.06)),
            self::xyzD65(),
        ));
    }

    /** ProPhoto RGB (ROMM RGB, ISO 22028-2), D50. */
    public static function prophotoRgb(): RgbSpace
    {
        return self::memo('prophoto-rgb', static fn(): RgbSpace => new RgbSpace('prophoto-rgb', 'ProPhoto RGB', new ProPhotoTransferFunction(), self::prophotoRgbLinear()));
    }

    public static function prophotoRgbLinear(): LinearRgbSpace
    {
        return self::memo('prophoto-rgb-linear', static fn(): LinearRgbSpace => new LinearRgbSpace(
            'prophoto-rgb-linear',
            'ProPhoto RGB (linear)',
            new RgbPrimaries(new Chromaticity(0.734699, 0.265301), new Chromaticity(0.159597, 0.840403), new Chromaticity(0.036598, 0.000105)),
            self::xyzD50(),
        ));
    }

    /** ITU-R BT.2020 primaries, D65; transfer function as CSS Color 4 defines `rec2020` (BT.1886, γ 2.4). */
    public static function rec2020(): RgbSpace
    {
        return self::memo('rec2020', static fn(): RgbSpace => new RgbSpace('rec2020', 'Rec. 2020', new GammaTransferFunction(2.4), self::rec2020Linear()));
    }

    public static function rec2020Linear(): LinearRgbSpace
    {
        return self::memo('rec2020-linear', static fn(): LinearRgbSpace => new LinearRgbSpace(
            'rec2020-linear',
            'Rec. 2020 (linear)',
            new RgbPrimaries(new Chromaticity(0.708, 0.292), new Chromaticity(0.170, 0.797), new Chromaticity(0.131, 0.046)),
            self::xyzD65(),
        ));
    }

    public static function hsl(): HslSpace
    {
        return self::memo('hsl', static fn(): HslSpace => new HslSpace(self::srgb()));
    }

    public static function hwb(): HwbSpace
    {
        return self::memo('hwb', static fn(): HwbSpace => new HwbSpace(self::srgb()));
    }

    public static function hsv(): HsvSpace
    {
        return self::memo('hsv', static fn(): HsvSpace => new HsvSpace(self::srgb()));
    }

    /** CIE Lab relative to D50, as CSS `lab()`. */
    public static function lab(): LabSpace
    {
        return self::memo('lab', static fn(): LabSpace => new LabSpace('lab', 'CIE Lab (D50)', self::xyzD50()));
    }

    /** CIE LCh(ab) relative to D50, as CSS `lch()`. */
    public static function lch(): PolarSpace
    {
        return self::memo('lch', static fn(): PolarSpace => new PolarSpace('lch', 'CIE LCh (D50)', self::lab(), 0.0015, 150.0));
    }

    /** CIE Lab relative to D65 (not a CSS space). */
    public static function labD65(): LabSpace
    {
        return self::memo('lab-d65', static fn(): LabSpace => new LabSpace('lab-d65', 'CIE Lab (D65)', self::xyzD65()));
    }

    /** CIE LCh(ab) relative to D65 (not a CSS space). */
    public static function lchD65(): PolarSpace
    {
        return self::memo('lch-d65', static fn(): PolarSpace => new PolarSpace('lch-d65', 'CIE LCh (D65)', self::labD65(), 0.0015, 150.0));
    }

    public static function oklab(): OklabSpace
    {
        return self::memo('oklab', static fn(): OklabSpace => new OklabSpace(self::xyzD65()));
    }

    public static function oklch(): PolarSpace
    {
        return self::memo('oklch', static fn(): PolarSpace => new PolarSpace('oklch', 'OKLCh', self::oklab(), 0.000004, 0.4));
    }

    /**
     * All built-in spaces.
     *
     * @return list<ColorSpace>
     */
    public static function all(): array
    {
        return [
            self::xyzD65(), self::xyzD50(),
            self::srgb(), self::srgbLinear(),
            self::displayP3(), self::displayP3Linear(),
            self::a98Rgb(), self::a98RgbLinear(),
            self::prophotoRgb(), self::prophotoRgbLinear(),
            self::rec2020(), self::rec2020Linear(),
            self::hsl(), self::hwb(), self::hsv(),
            self::lab(), self::lch(), self::labD65(), self::lchD65(),
            self::oklab(), self::oklch(),
        ];
    }

    /**
     * @template T of ColorSpace
     *
     * @param \Closure(): T $factory
     *
     * @return T
     */
    private static function memo(string $id, \Closure $factory): ColorSpace
    {
        if (!isset(self::$instances[$id])) {
            self::$instances[$id] = $factory();
        }

        /** @var T */
        return self::$instances[$id];
    }
}
