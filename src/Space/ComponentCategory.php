<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space;

/**
 * Analogous component category of a channel (CSS Color 4 § 13.3, CRD 2026-09-26).
 *
 * Channels of two spaces with the same category are "analogous": a missing component (`none`)
 * is carried forward across a conversion between the two spaces instead of becoming 0. It is
 * used by interpolation, `color-mix()` and relative color syntax.
 *
 * XYZ counts as a super-saturated RGB space (x = Reds, y = Greens, z = Blues). HWB whiteness and
 * blackness have no category. HSV is not a CSS space; the library puts its saturation in
 * Colorfulness like HSL saturation, and its value in no category.
 */
enum ComponentCategory: string
{
    case Reds = 'reds';
    case Greens = 'greens';
    case Blues = 'blues';
    case Lightness = 'lightness';
    /** Chroma (C) and saturation (S). */
    case Colorfulness = 'colorfulness';
    case Hue = 'hue';
    case OpponentA = 'opponent-a';
    case OpponentB = 'opponent-b';
}
