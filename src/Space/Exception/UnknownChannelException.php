<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Space\Exception;

use Domm98CZ\Color\Exception\InvalidArgumentException;

/**
 * The requested channel (by name or index) does not exist in the color space.
 */
final class UnknownChannelException extends InvalidArgumentException
{
    public static function in(string $spaceId, int|string $channel): self
    {
        return new self(\sprintf('Color space "%s" has no channel %s.', $spaceId, \is_int($channel) ? '#' . $channel : '"' . $channel . '"'));
    }
}
