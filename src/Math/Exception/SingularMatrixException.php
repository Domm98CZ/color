<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Math\Exception;

use Domm98CZ\Color\Exception\DomainException;

/**
 * The matrix is singular or too close to singular to be inverted reliably.
 */
final class SingularMatrixException extends DomainException
{
    public static function withDeterminant(float $determinant, float $threshold): self
    {
        return new self(\sprintf(
            'The matrix cannot be inverted: |determinant| %s is not above the singularity threshold %s.',
            \var_export(\abs($determinant), true),
            \var_export($threshold, true),
        ));
    }
}
