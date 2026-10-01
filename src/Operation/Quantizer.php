<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Operation;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Notation\Exception\OutOfGamutException;
use Domm98CZ\Color\Operation\Exception\InvalidOperationException;
use Domm98CZ\Color\Operation\Exception\UnsupportedOperationSpaceException;
use Domm98CZ\Color\Operation\Result\QuantizedColor;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Quantises a color to integer codes of 1–16 bits per channel in a bounded RGB space (default
 * sRGB): code = ⌊v · (2^bits − 1) + 0.5⌋, i.e. rounding half towards +∞, as {@see Color::toHex()}
 * does for 8 bits. The alpha is quantised with the same depth.
 *
 * - The color is converted to the space first. A channel outside [0, 1] by more than
 *   {@see Color::DEFAULT_GAMUT_TOLERANCE} raises {@see OutOfGamutException}: quantisation never
 *   clips silently. Map the color into the gamut first.
 * - Missing components and a missing alpha are quantised as 0 (CSS Color 4 § 4.4).
 * - Codes are full-range (0 = 0.0, max = 1.0), not the "video range" of broadcast Y′CbCr.
 * - No dithering.
 */
final readonly class Quantizer
{
    public const int MIN_BITS = 1;
    public const int MAX_BITS = 16;

    public ColorSpace $space;

    /**
     * @param ColorSpace|null $space a bounded RGB space (its own gamut space); default sRGB
     *
     * @throws InvalidOperationException when the depth is not 1–16 bits
     * @throws UnsupportedOperationSpaceException when the space is not a bounded RGB space
     */
    public function __construct(public int $bits = 8, ?ColorSpace $space = null)
    {
        if ($bits < self::MIN_BITS || $bits > self::MAX_BITS) {
            throw new InvalidOperationException(\sprintf('The bit depth must be between %d and %d, %d given.', self::MIN_BITS, self::MAX_BITS, $bits));
        }

        $space ??= ColorSpaces::srgb();
        if ($space->gamutSpace()?->id() !== $space->id()) {
            throw new UnsupportedOperationSpaceException(\sprintf('Quantisation needs a bounded RGB space whose [0, 1] cube is its gamut; "%s" is not one.', $space->id()));
        }

        $this->space = $space;
    }

    /**
     * @throws OutOfGamutException when the color is outside the gamut of the space
     */
    public function quantize(Color $color): QuantizedColor
    {
        $converted = $color->to($this->space);
        $max = (1 << $this->bits) - 1;
        $codes = [];

        foreach ($converted->coordinates() as $value) {
            $value ??= 0.0;

            if ($value < -Color::DEFAULT_GAMUT_TOLERANCE || $value > 1.0 + Color::DEFAULT_GAMUT_TOLERANCE) {
                throw new OutOfGamutException($color->space(), $color->coordinates(), $this->space, \sprintf(
                    'The color %s is outside the %s gamut and cannot be quantised without changing it. Map it into the gamut first.',
                    $color->toCss(),
                    $this->space->id(),
                ));
            }

            $codes[] = self::code($value, $max);
        }

        $alphaCode = self::code($color->alpha(), $max);

        return new QuantizedColor(
            $codes,
            $alphaCode,
            $this->bits,
            Color::of($this->space, \array_map(static fn(int $code): float => $code / $max, $codes), $alphaCode / $max),
        );
    }

    private static function code(float $value, int $max): int
    {
        return (int) \floor(\max(0.0, \min(1.0, $value)) * $max + 0.5);
    }
}
