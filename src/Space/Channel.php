<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space;

/**
 * Description of one coordinate of a color space.
 *
 * The reference range is the range CSS uses to map percentages (e.g. OKLCh chroma 100% = 0.4);
 * for bounded RGB spaces it is also the gamut range. Values outside the reference range are valid.
 */
final readonly class Channel
{
    /**
     * @param non-empty-string $name short machine name, e.g. 'l', 'c', 'h'
     * @param non-empty-string $label human-readable name, e.g. 'Lightness'
     * @param ComponentCategory|null $category analogous component category (CSS Color 4 § 13.3), or
     *                                         null when the channel has no analog in other spaces
     */
    public function __construct(
        public string $name,
        public string $label,
        public ChannelKind $kind,
        public float $referenceMin,
        public float $referenceMax,
        public ?ComponentCategory $category = null,
    ) {}

    public function isHue(): bool
    {
        return $this->kind === ChannelKind::Hue;
    }
}
