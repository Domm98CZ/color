<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Palette\Scale;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Gamut\Contract\GamutMapper;
use Domm98CZ\Color\Palette\Contract\ScaleGenerator;
use Domm98CZ\Color\Palette\Exception\InvalidPaletteException;
use Domm98CZ\Color\Palette\Internal\Steps;
use Domm98CZ\Color\Palette\Result\GeneratedScale;
use Domm98CZ\Color\Space\ComponentCategory;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * The base with its lightness set to each given value, in a space with a lightness channel
 * (OKLCh, OKLab, CIE LCh/Lab, HSL): the typical design-system scale 50…900. The other channels of
 * the space are kept, so steps may leave the gamut; pass a mapper to map them.
 */
final readonly class LightnessScale implements ScaleGenerator
{
    private int $channel;

    /**
     * @param non-empty-list<float> $lightness values in the units of the space (OKLCh 0–1, CIE L* 0–100…)
     *
     * @throws InvalidPaletteException when the space has no lightness channel or a value is not finite
     */
    public function __construct(
        private ColorSpace $space,
        private array $lightness,
    ) {
        $channel = \array_search(ComponentCategory::Lightness, \array_map(static fn($definition): ?ComponentCategory => $definition->category, $space->channels()), true);

        if ($channel === false) {
            throw new InvalidPaletteException(\sprintf('The space "%s" has no lightness channel.', $space->id()));
        }

        foreach ($lightness as $value) {
            if (!\is_finite($value)) {
                throw new InvalidPaletteException(\sprintf('Lightness values must be finite, %s given.', \var_export($value, true)));
            }
        }

        $this->channel = $channel;
    }

    public function generate(Color $base, ?GamutMapper $mapper = null, ?ColorSpace $gamut = null): GeneratedScale
    {
        $coordinates = $base->to($this->space)->coordinates();
        $alpha = $base->isAlphaMissing() ? null : $base->alpha();
        $colors = [];

        foreach ($this->lightness as $value) {
            $coordinates[$this->channel] = $value;
            $colors[] = Color::of($this->space, $coordinates, $alpha);
        }

        return Steps::scale($colors, $mapper, $gamut);
    }
}
