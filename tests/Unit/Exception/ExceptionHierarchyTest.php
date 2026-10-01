<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Exception;

use Domm98CZ\Color\Exception\ColorException;
use Domm98CZ\Color\Exception\ConfigurationException;
use Domm98CZ\Color\Exception\DomainException;
use Domm98CZ\Color\Exception\InsufficientDataException;
use Domm98CZ\Color\Exception\InvalidArgumentException;
use Domm98CZ\Color\Math\Exception\InvalidDimensionException;
use Domm98CZ\Color\Math\Exception\InvalidToleranceException;
use Domm98CZ\Color\Math\Exception\NonFiniteValueException;
use Domm98CZ\Color\Math\Exception\OutOfDomainException;
use Domm98CZ\Color\Math\Exception\SingularMatrixException;
use Domm98CZ\Color\Provenance\Exception\InvalidProvenanceException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExceptionHierarchyTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, class-string}>
     */
    public static function categories(): iterable
    {
        yield 'invalid argument' => [InvalidArgumentException::class, \InvalidArgumentException::class];
        yield 'domain' => [DomainException::class, \DomainException::class];
        yield 'insufficient data' => [InsufficientDataException::class, \DomainException::class];
        yield 'configuration' => [ConfigurationException::class, \LogicException::class];
    }

    /**
     * @param class-string $category
     * @param class-string $splParent
     */
    #[DataProvider('categories')]
    public function testCategoryImplementsMarkerAndExtendsSplException(string $category, string $splParent): void
    {
        self::assertTrue(\is_subclass_of($category, ColorException::class));
        self::assertTrue(\is_subclass_of($category, $splParent));
    }

    /**
     * @return iterable<string, array{class-string, class-string}>
     */
    public static function concreteExceptions(): iterable
    {
        yield 'non-finite value' => [NonFiniteValueException::class, InvalidArgumentException::class];
        yield 'invalid dimension' => [InvalidDimensionException::class, InvalidArgumentException::class];
        yield 'invalid tolerance' => [InvalidToleranceException::class, InvalidArgumentException::class];
        yield 'singular matrix' => [SingularMatrixException::class, DomainException::class];
        yield 'out of domain' => [OutOfDomainException::class, DomainException::class];
        yield 'invalid provenance' => [InvalidProvenanceException::class, InvalidArgumentException::class];
    }

    /**
     * @param class-string $exception
     * @param class-string $category
     */
    #[DataProvider('concreteExceptions')]
    public function testConcreteExceptionBelongsToCategory(string $exception, string $category): void
    {
        self::assertTrue(\is_subclass_of($exception, $category));
        self::assertTrue(\is_subclass_of($exception, ColorException::class));
    }

    public function testInsufficientDataExceptionExposesMissingInputs(): void
    {
        $previous = new \RuntimeException('cause');
        $exception = new class ('Contrast needs an opaque backdrop.', ['opaque backdrop'], $previous) extends InsufficientDataException {};

        self::assertSame(['opaque backdrop'], $exception->getMissing());
        self::assertSame('Contrast needs an opaque backdrop.', $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(0, $exception->getCode());
    }
}
