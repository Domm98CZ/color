<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette\Scale;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Gamut\Contract\GamutMapper;
use Domm98CZ\Color\Operation\TintShadeTone;
use Domm98CZ\Color\Palette\Contract\ScaleGenerator;
use Domm98CZ\Color\Palette\Exception\InvalidPaletteException;
use Domm98CZ\Color\Palette\Internal\Steps;
use Domm98CZ\Color\Palette\Result\GeneratedScale;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Tints, shades or tones of the base: $steps colors, the base first, then mixtures with white,
 * black or gray in amounts k / $steps (k = 0 … steps − 1), i.e. up to but not including the pure
 * white, black or gray. See {@see TintShadeTone} for the mixing.
 */
final readonly class MixScale implements ScaleGenerator
{
    private TintShadeTone $mixer;

    /**
     * @param Color|null $gray the gray of tones (ignored otherwise); default: the neutral with the
     *                         base's OKLab lightness
     *
     * @throws InvalidPaletteException when fewer than 2 steps are asked for
     */
    public function __construct(
        private MixKind $kind,
        ColorSpace $space,
        private int $steps,
        private ?Color $gray = null,
    ) {
        if ($steps < 2) {
            throw new InvalidPaletteException(\sprintf('A scale needs at least 2 steps, %d given.', $steps));
        }

        $this->mixer = new TintShadeTone($space);
    }

    public function generate(Color $base, ?GamutMapper $mapper = null, ?ColorSpace $gamut = null): GeneratedScale
    {
        $colors = [];
        for ($k = 0; $k < $this->steps; $k++) {
            $amount = $k / $this->steps;
            $colors[] = match ($this->kind) {
                MixKind::Tint => $this->mixer->tint($base, $amount),
                MixKind::Shade => $this->mixer->shade($base, $amount),
                MixKind::Tone => $this->mixer->tone($base, $amount, $this->gray),
            };
        }

        return Steps::scale($colors, $mapper, $gamut);
    }
}
