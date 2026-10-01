<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Provenance;

/**
 * Assumptions and warnings attached to a result. Immutable; `with*()` returns a new instance.
 */
final readonly class Diagnostics
{
    /**
     * @param list<Assumption> $assumptions
     * @param list<Warning> $warnings
     */
    private function __construct(
        public array $assumptions,
        public array $warnings,
    ) {}

    public static function none(): self
    {
        return new self([], []);
    }

    /**
     * @param list<Assumption> $assumptions
     * @param list<Warning> $warnings
     */
    public static function of(array $assumptions = [], array $warnings = []): self
    {
        return new self($assumptions, $warnings);
    }

    public function withAssumption(Assumption $assumption): self
    {
        return new self([...$this->assumptions, $assumption], $this->warnings);
    }

    public function withWarning(Warning $warning): self
    {
        return new self($this->assumptions, [...$this->warnings, $warning]);
    }

    /**
     * Concatenates both diagnostics, keeping order ($this first).
     */
    public function merge(self $other): self
    {
        return new self([...$this->assumptions, ...$other->assumptions], [...$this->warnings, ...$other->warnings]);
    }

    public function isEmpty(): bool
    {
        return $this->assumptions === [] && $this->warnings === [];
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }

    public function hasWarning(WarningCode $code): bool
    {
        foreach ($this->warnings as $warning) {
            if ($warning->code === $code) {
                return true;
            }
        }

        return false;
    }
}
