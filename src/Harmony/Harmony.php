<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Harmony;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Harmony\Contract\HueWheel;
use Domm98CZ\Color\Harmony\Exception\InvalidHarmonyException;
use Domm98CZ\Color\Harmony\Result\HarmonyMember;
use Domm98CZ\Color\Harmony\Result\HarmonySet;
use Domm98CZ\Color\Provenance\Diagnostics;
use Domm98CZ\Color\Provenance\Warning;
use Domm98CZ\Color\Provenance\WarningCode;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Harmony schemes on an explicit wheel. Each member is the base color moved on the wheel by the
 * scheme's offset ({@see HueWheel::colorAt()}); members are in the base color's space, never
 * clipped, and marked in or out of $gamut (default sRGB).
 *
 * A base without hue (gray) has no place on a wheel: every member is the base, with a
 * `hue-undefined` warning.
 *
 *     Harmony::complementary($color, HueWheels::oklch());
 *     Harmony::of($color, HueWheels::ryb(), HarmonyScheme::splitComplementary(30.0));
 */
final class Harmony
{
    private function __construct() {}

    public static function of(Color $base, HueWheel $wheel, HarmonyScheme $scheme, ?ColorSpace $gamut = null): HarmonySet
    {
        $gamut ??= ColorSpaces::srgb();
        $hue = $wheel->hueOf($base);
        $diagnostics = Diagnostics::none();

        if ($hue === null) {
            $diagnostics = $diagnostics->withWarning(new Warning(WarningCode::HueUndefined, 'The base color has no hue on this wheel; every member is the base color.', ['wheel' => $wheel->id()->identifier()]));
        }

        $members = [];
        foreach ($scheme->offsets as $offset) {
            $color = $hue === null ? $base : $wheel->colorAt($hue + $offset, $base);
            $members[] = new HarmonyMember($color, $offset, $hue === null ? null : $wheel->hueOf($color), $color->isInGamut($gamut));
        }

        return new HarmonySet($base, $scheme, $wheel->id(), $gamut, $members, $diagnostics);
    }

    public static function complementary(Color $base, HueWheel $wheel, ?ColorSpace $gamut = null): HarmonySet
    {
        return self::of($base, $wheel, HarmonyScheme::complementary(), $gamut);
    }

    /**
     * @throws InvalidHarmonyException
     */
    public static function splitComplementary(Color $base, HueWheel $wheel, float $spread = 30.0, ?ColorSpace $gamut = null): HarmonySet
    {
        return self::of($base, $wheel, HarmonyScheme::splitComplementary($spread), $gamut);
    }

    /**
     * @throws InvalidHarmonyException
     */
    public static function analogous(Color $base, HueWheel $wheel, int $count = 3, float $step = 30.0, ?ColorSpace $gamut = null): HarmonySet
    {
        return self::of($base, $wheel, HarmonyScheme::analogous($count, $step), $gamut);
    }

    public static function triadic(Color $base, HueWheel $wheel, ?ColorSpace $gamut = null): HarmonySet
    {
        return self::of($base, $wheel, HarmonyScheme::triadic(), $gamut);
    }

    /**
     * @throws InvalidHarmonyException
     */
    public static function tetradic(Color $base, HueWheel $wheel, float $offset = 60.0, ?ColorSpace $gamut = null): HarmonySet
    {
        return self::of($base, $wheel, HarmonyScheme::tetradic($offset), $gamut);
    }

    public static function square(Color $base, HueWheel $wheel, ?ColorSpace $gamut = null): HarmonySet
    {
        return self::of($base, $wheel, HarmonyScheme::square(), $gamut);
    }
}
