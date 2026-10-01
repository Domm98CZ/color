<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Fluent;

use Domm98CZ\Color\Contrast\Contract\ContrastAlgorithm;
use Domm98CZ\Color\Contrast\Contrast;
use Domm98CZ\Color\Gamut\Contract\GamutMapper;
use Domm98CZ\Color\Gamut\GamutMappers;
use Domm98CZ\Color\Harmony\Contract\HueWheel;
use Domm98CZ\Color\Harmony\HueWheels;
use Domm98CZ\Color\Operation\ChromaModel;
use Domm98CZ\Color\Operation\HueModel;
use Domm98CZ\Color\Operation\LightnessModel;
use Domm98CZ\Color\Operation\Mixer;
use Domm98CZ\Color\Space\ColorSpaces;
use Domm98CZ\Color\Space\Contract\ColorSpace;

/**
 * Every method the fluent layer uses when a call does not name one. The defaults of
 * {@see defaults()} are part of the compatibility promise:
 *
 * | Setting | Default | Used by |
 * |---|---|---|
 * | `lightness` | `LightnessModel::OklchL` | `lighten()`, `darken()` |
 * | `chroma` | `ChromaModel::Oklch` | `scaleChroma()` |
 * | `hue` | `HueModel::Oklch` | `rotateHue()` |
 * | `mixer` | `new Mixer()` (OKLab, shorter hue) | `mix()`, `tint()`, `shade()`, `tone()` |
 * | `wheel` | `HueWheels::oklch()` | `complement()`, `harmony()` |
 * | `outputSpace` | sRGB | `toColor()`, `toHex()`, `toCss()`, `contrastWith()`, `harmony()` (gamut) |
 * | `outputMapper` | `GamutMappers::cssColor4()`; `null` = no mapping | the same |
 * | `contrast` | `Contrast::wcag2()` | `contrastWith()` |
 */
final readonly class FluentConfig
{
    private function __construct(
        public LightnessModel $lightness,
        public ChromaModel $chroma,
        public HueModel $hue,
        public Mixer $mixer,
        public HueWheel $wheel,
        public ColorSpace $outputSpace,
        public ?GamutMapper $outputMapper,
        public ContrastAlgorithm $contrast,
    ) {}

    public static function defaults(): self
    {
        return new self(
            LightnessModel::OklchL,
            ChromaModel::Oklch,
            HueModel::Oklch,
            new Mixer(),
            HueWheels::oklch(),
            ColorSpaces::srgb(),
            GamutMappers::cssColor4(),
            Contrast::wcag2(),
        );
    }

    public function withLightness(LightnessModel $model): self
    {
        return new self($model, $this->chroma, $this->hue, $this->mixer, $this->wheel, $this->outputSpace, $this->outputMapper, $this->contrast);
    }

    public function withChroma(ChromaModel $model): self
    {
        return new self($this->lightness, $model, $this->hue, $this->mixer, $this->wheel, $this->outputSpace, $this->outputMapper, $this->contrast);
    }

    public function withHue(HueModel $model): self
    {
        return new self($this->lightness, $this->chroma, $model, $this->mixer, $this->wheel, $this->outputSpace, $this->outputMapper, $this->contrast);
    }

    public function withMixer(Mixer $mixer): self
    {
        return new self($this->lightness, $this->chroma, $this->hue, $mixer, $this->wheel, $this->outputSpace, $this->outputMapper, $this->contrast);
    }

    public function withWheel(HueWheel $wheel): self
    {
        return new self($this->lightness, $this->chroma, $this->hue, $this->mixer, $wheel, $this->outputSpace, $this->outputMapper, $this->contrast);
    }

    public function withOutputSpace(ColorSpace $space): self
    {
        return new self($this->lightness, $this->chroma, $this->hue, $this->mixer, $this->wheel, $space, $this->outputMapper, $this->contrast);
    }

    /**
     * `null` switches mapping off: outputs are only converted, `toHex()` then throws for colors
     * outside sRGB.
     */
    public function withOutputMapping(?GamutMapper $mapper): self
    {
        return new self($this->lightness, $this->chroma, $this->hue, $this->mixer, $this->wheel, $this->outputSpace, $mapper, $this->contrast);
    }

    public function withContrast(ContrastAlgorithm $algorithm): self
    {
        return new self($this->lightness, $this->chroma, $this->hue, $this->mixer, $this->wheel, $this->outputSpace, $this->outputMapper, $algorithm);
    }
}
