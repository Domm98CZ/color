<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Architecture;

use Domm98CZ\Color\Exception\ColorException;
use Domm98CZ\Color\Exception\ConfigurationException;
use Domm98CZ\Color\Exception\DomainException;
use Domm98CZ\Color\Exception\InvalidArgumentException;
use Domm98CZ\Color\Tests\Support\Api\PublicApi;
use PHPUnit\Framework\TestCase;

/**
 * Rules of the public API shape and the error model that PHPStan does not check.
 */
final class ArchitectureRulesTest extends TestCase
{
    private const string SOURCE = __DIR__ . '/../../../src';

    /** @var list<class-string> the exception categories of the error model */
    private const array EXCEPTION_CATEGORIES = [
        InvalidArgumentException::class,
        DomainException::class,
        ConfigurationException::class,
    ];

    public function testEveryExceptionIsALibraryExceptionOfOneCategory(): void
    {
        $checked = 0;
        foreach (self::classes() as $class) {
            if ($class->isInterface() || !$class->isSubclassOf(\Throwable::class)) {
                continue;
            }
            self::assertTrue($class->implementsInterface(ColorException::class), $class->getName() . ' implements ColorException');
            $categories = \array_filter(self::EXCEPTION_CATEGORIES, static fn(string $category): bool => $class->getName() === $category || $class->isSubclassOf($category));
            self::assertCount(1, $categories, $class->getName() . ' belongs to exactly one category');
            $checked++;
        }
        self::assertGreaterThan(20, $checked);
    }

    public function testConcreteClassesAreFinal(): void
    {
        foreach (self::classes() as $class) {
            if ($class->isInterface() || $class->isEnum() || $class->isAbstract()) {
                continue;
            }
            self::assertTrue($class->isFinal(), $class->getName() . ' is final');
        }
    }

    public function testOnlyTheExceptionCategoriesAreAbstract(): void
    {
        $abstract = [];
        foreach (self::classes() as $class) {
            if ($class->isAbstract() && !$class->isInterface()) {
                $abstract[] = $class->getName();
            }
        }
        \sort($abstract);

        self::assertSame([
            ConfigurationException::class,
            DomainException::class,
            'Domm98CZ\Color\Exception\InsufficientDataException',
            InvalidArgumentException::class,
            'Domm98CZ\Color\Fluent\FluentColor',
        ], $abstract);
    }

    public function testPublicPropertiesOfValueObjectsAreReadonly(): void
    {
        foreach (self::classes() as $class) {
            if ($class->isSubclassOf(\Throwable::class) || $class->isEnum()) {
                continue;
            }
            foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                self::assertTrue($property->isReadOnly(), $class->getName() . '::$' . $property->getName() . ' is readonly');
            }
        }
    }

    public function testInternalClassesAreMarked(): void
    {
        $checked = 0;
        foreach (self::classes() as $class) {
            if (!\str_contains($class->getName(), '\\Internal\\')) {
                continue;
            }
            self::assertStringContainsString('@internal', (string) $class->getDocComment(), $class->getName() . ' is marked @internal');
            $checked++;
        }
        self::assertGreaterThan(10, $checked);
    }

    public function testThePublicApiDoesNotExposeInternalTypes(): void
    {
        foreach (self::classes() as $class) {
            if (PublicApi::isInternal($class)) {
                continue;
            }
            foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if (\str_contains((string) $method->getDocComment(), '@internal')) {
                    continue;
                }
                $types = [$method->getReturnType()];
                foreach ($method->getParameters() as $parameter) {
                    $types[] = $parameter->getType();
                }
                foreach ($types as $type) {
                    self::assertStringNotContainsString('\\Internal\\', (string) $type, $class->getName() . '::' . $method->getName() . '()');
                }
            }
            foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                self::assertStringNotContainsString('\\Internal\\', (string) $property->getType(), $class->getName() . '::$' . $property->getName());
            }
        }
    }

    /**
     * @return list<\ReflectionClass<object>>
     */
    private static function classes(): array
    {
        return \array_map(static fn(string $name): \ReflectionClass => new \ReflectionClass($name), PublicApi::declarations(self::SOURCE));
    }
}
