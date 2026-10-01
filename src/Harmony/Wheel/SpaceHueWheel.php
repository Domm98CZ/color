<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Harmony\Wheel;

use Domm98CZ\Color\Color;
use Domm98CZ\Color\Harmony\Contract\HueWheel;
use Domm98CZ\Color\Harmony\Exception\InvalidHarmonyException;
use Domm98CZ\Color\Math\Angle;
use Domm98CZ\Color\Provenance\AlgorithmId;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * The hue channel of a polar color space as a wheel (OKLCh, CIE LCh, HSL): the color's hue in that
 * space; moving a color keeps its other channels (e.g. OKLCh L and C), so the result can leave the
 * gamut of the template's space.
 */
final readonly class SpaceHueWheel implements HueWheel
{
    private int $channel;

    /**
     * @throws InvalidHarmonyException when the space has no hue channel
     */
    public function __construct(
        private ColorSpace $space,
        private AlgorithmId $id,
    ) {
        foreach ($space->channels() as $index => $channel) {
            if ($channel->isHue()) {
                $this->channel = $index;

                return;
            }
        }

        throw new InvalidHarmonyException(\sprintf('The space "%s" has no hue channel.', $space->id()));
    }

    public function id(): AlgorithmId
    {
        return $this->id;
    }

    public function space(): ColorSpace
    {
        return $this->space;
    }

    public function hueOf(Color $color): ?float
    {
        return $color->to($this->space)->coordinates()[$this->channel];
    }

    public function colorAt(float $angle, Color $template): Color
    {
        if (!\is_finite($angle)) {
            throw new InvalidHarmonyException(\sprintf('A wheel angle must be finite, %s given.', \var_export($angle, true)));
        }

        $coordinates = $template->to($this->space)->coordinates();
        $coordinates[$this->channel] = Angle::normalizeDegrees($angle);

        return Color::of($this->space, $coordinates, $template->isAlphaMissing() ? null : $template->alpha())->to($template->space());
    }
}
