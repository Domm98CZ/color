<?php

declare(strict_types=1);

namespace Domm98CZ\Color\Tests\Unit\Provenance;

use Domm98CZ\Color\Provenance\Assumption;
use Domm98CZ\Color\Provenance\Diagnostics;
use Domm98CZ\Color\Provenance\Exception\InvalidProvenanceException;
use Domm98CZ\Color\Provenance\Warning;
use Domm98CZ\Color\Provenance\WarningCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DiagnosticsTest extends TestCase
{
    public function testNoneIsEmpty(): void
    {
        $diagnostics = Diagnostics::none();

        self::assertTrue($diagnostics->isEmpty());
        self::assertFalse($diagnostics->hasWarnings());
        self::assertSame([], $diagnostics->assumptions);
        self::assertSame([], $diagnostics->warnings);
    }

    public function testWithMethodsAreImmutable(): void
    {
        $empty = Diagnostics::none();
        $assumption = new Assumption('adapted-to-d50', 'Converted with linear Bradford.');
        $warning = new Warning(WarningCode::AlphaIgnored, 'Alpha 0.5 ignored.');

        $withAssumption = $empty->withAssumption($assumption);
        $withBoth = $withAssumption->withWarning($warning);

        self::assertTrue($empty->isEmpty());
        self::assertSame([$assumption], $withAssumption->assumptions);
        self::assertFalse($withAssumption->hasWarnings());
        self::assertSame([$assumption], $withBoth->assumptions);
        self::assertSame([$warning], $withBoth->warnings);
        self::assertTrue($withBoth->hasWarning(WarningCode::AlphaIgnored));
        self::assertFalse($withBoth->hasWarning(WarningCode::HueUndefined));
    }

    public function testWithMethodsAppend(): void
    {
        $a1 = new Assumption('a-one', 'First.');
        $a2 = new Assumption('a-two', 'Second.');
        $w1 = new Warning(WarningCode::HueUndefined, 'Hue undefined.');
        $w2 = new Warning(WarningCode::AlphaIgnored, 'Alpha ignored.');

        $diagnostics = Diagnostics::none()->withAssumption($a1)->withAssumption($a2)->withWarning($w1)->withWarning($w2);

        self::assertSame([$a1, $a2], $diagnostics->assumptions);
        self::assertSame([$w1, $w2], $diagnostics->warnings);
    }

    public function testMergeKeepsAllItemsInOrder(): void
    {
        $a = [new Assumption('a-one', 'One.'), new Assumption('a-two', 'Two.'), new Assumption('a-three', 'Three.'), new Assumption('a-four', 'Four.')];
        $w = [
            new Warning(WarningCode::HueUndefined, 'One.'),
            new Warning(WarningCode::AlphaIgnored, 'Two.'),
            new Warning(WarningCode::HueUndefined, 'Three.'),
            new Warning(WarningCode::AlphaIgnored, 'Four.'),
        ];

        $merged = Diagnostics::of([$a[0], $a[1]], [$w[0], $w[1]])->merge(Diagnostics::of([$a[2], $a[3]], [$w[2], $w[3]]));

        self::assertSame($a, $merged->assumptions);
        self::assertSame($w, $merged->warnings);
    }

    public function testAssumptionValidation(): void
    {
        $assumption = new Assumption('observer-2-degree', 'CIE 1931 2° observer.');
        self::assertSame('observer-2-degree', $assumption->code);
        self::assertSame('CIE 1931 2° observer.', $assumption->description);

        $this->expectException(InvalidProvenanceException::class);
        new Assumption('observer', '   ');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAssumptionCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Observer'];
        yield 'space' => ['two degree'];
        yield 'dot' => ['a.b'];
    }

    #[DataProvider('invalidAssumptionCodes')]
    public function testRejectsInvalidAssumptionCodes(string $code): void
    {
        $this->expectException(InvalidProvenanceException::class);

        /** @phpstan-ignore argument.type (deliberately invalid input) */
        new Assumption($code, 'Description.');
    }

    public function testWarningKeepsContext(): void
    {
        $warning = new Warning(WarningCode::HueUndefined, 'No hue on this wheel.', ['wheel' => 'hue-wheel-oklch', 'chroma' => 0.0, 'achromatic' => true, 'note' => null]);

        self::assertSame(WarningCode::HueUndefined, $warning->code);
        self::assertSame('No hue on this wheel.', $warning->message);
        self::assertSame(['wheel' => 'hue-wheel-oklch', 'chroma' => 0.0, 'achromatic' => true, 'note' => null], $warning->context);
    }

    public function testWarningRejectsBlankMessage(): void
    {
        $this->expectException(InvalidProvenanceException::class);

        new Warning(WarningCode::AlphaIgnored, ' ');
    }

    public function testWarningRejectsNonStringContextKeys(): void
    {
        $this->expectException(InvalidProvenanceException::class);

        /** @phpstan-ignore argument.type (deliberately invalid input) */
        new Warning(WarningCode::AlphaIgnored, 'Message.', [0 => 'value']);
    }

    public function testWarningRejectsNonScalarContextValues(): void
    {
        $this->expectException(InvalidProvenanceException::class);

        /** @phpstan-ignore argument.type (deliberately invalid input) */
        new Warning(WarningCode::AlphaIgnored, 'Message.', ['nested' => ['a' => 1]]);
    }
}
