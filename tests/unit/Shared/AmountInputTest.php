<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\AmountInput;
use Codeception\Test\Unit;

final class AmountInputTest extends Unit
{
    public function testReadsGermanAndPlainNotation(): void
    {
        $this->assertSame('1234.50', AmountInput::normalize('1.234,50 €'));
        $this->assertSame('25000.00', AmountInput::normalize('25.000'));
        $this->assertSame('1250000.00', AmountInput::normalize('1.250.000'));
        $this->assertSame('25000.00', AmountInput::normalize('25000'));
        $this->assertSame('99.90', AmountInput::normalize('99.90'));
        $this->assertSame('12.50', AmountInput::normalize('12,5'));
        $this->assertSame('0.00', AmountInput::normalize('0'));
        $this->assertNull(AmountInput::normalize('  '));
        $this->assertNull(AmountInput::normalize(null));
    }

    /** @dataProvider invalidInputs */
    public function testRejectsInvalidInput(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AmountInput::normalize($input);
    }

    /** @return array<string, array{string}> */
    public static function invalidInputs(): array
    {
        return ['Text' => ['abc'], 'negativ' => ['-5'], 'zwei Kommas' => ['1,2,3'], 'Exponent' => ['1e5'], 'zu groß' => ['99999999999']];
    }
}
