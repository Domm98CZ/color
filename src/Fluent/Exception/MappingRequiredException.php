<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Fluent\Exception;

use Domm98CZ\Color\Exception\ConfigurationException;

/**
 * A fluent method needs a gamut mapper (the colors it tries leave sRGB by construction), but
 * output mapping is switched off in the configuration. Configure a mapper for that chain.
 */
final class MappingRequiredException extends ConfigurationException
{
    public static function for(string $method): self
    {
        return new self(\sprintf('%s() needs a gamut mapper, but output mapping is switched off; use FluentConfig::withOutputMapping(GamutMappers::cssColor4()) or another mapper.', $method));
    }
}
