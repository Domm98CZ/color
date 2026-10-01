<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Provenance;

use Domm98CZ\Color\Provenance\WarningCode;
use PHPUnit\Framework\TestCase;

final class WarningCodeTest extends TestCase
{
    public function testValuesAreKebabCaseAndUnique(): void
    {
        $values = \array_map(static fn(WarningCode $code): string => $code->value, WarningCode::cases());

        foreach ($values as $value) {
            self::assertMatchesRegularExpression('~^[a-z]+(?:-[a-z0-9]+)*$~', $value);
        }

        self::assertSame(\array_values(\array_unique($values)), $values);
    }

    public function testEveryCodeHasADescription(): void
    {
        foreach (WarningCode::cases() as $code) {
            self::assertNotSame('', \trim($code->description()), $code->name);
        }
    }
}
