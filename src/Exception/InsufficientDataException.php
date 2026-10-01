<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Exception;

/**
 * The operation needs input that was not provided (a backdrop for a translucent color,
 * a profile for device values, measurement conditions…). The library never substitutes
 * a guess for the missing data.
 */
abstract class InsufficientDataException extends DomainException
{
    /**
     * @param non-empty-list<non-empty-string> $missing human-readable names of the missing inputs
     */
    public function __construct(
        string $message,
        private readonly array $missing,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @return non-empty-list<non-empty-string>
     */
    public function getMissing(): array
    {
        return $this->missing;
    }
}
