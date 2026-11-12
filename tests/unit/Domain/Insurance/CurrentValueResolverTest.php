<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Insurance;

use App\Domain\Insurance\CurrentValueResolver;
use App\Domain\Insurance\InsuranceField;
use App\Domain\InventoryNumber\InventoryNumber;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class CurrentValueResolverTest extends Unit
{
    public function testOwnValueOfTheInventoryNumberWinsOverTheArticleFixedValue(): void
    {
        $inv = $this->inventoryNumber();
        $inv->setCurrentValue('120.00');

        $this->assertSame(['value' => '120.00', 'origin' => CurrentValueResolver::ORIGIN_ITEM], CurrentValueResolver::resolve($inv, '15.00'));
    }

    public function testAiEstimateIsMarkedAsSuchAndKeepsItsReasoning(): void
    {
        $inv = $this->inventoryNumber();
        $inv->setCurrentValue('80.00', InventoryNumber::CURRENT_VALUE_AI, 'Begründung');

        $this->assertSame(CurrentValueResolver::ORIGIN_AI, CurrentValueResolver::resolve($inv, null)['origin']);
        $this->assertSame('Begründung', $inv->currentValueNote);
        $this->assertNotNull($inv->currentValueUpdatedAt);
    }

    public function testClearingTheValueAlsoClearsSourceAndReasoning(): void
    {
        $inv = $this->inventoryNumber();
        $inv->setCurrentValue('80.00', InventoryNumber::CURRENT_VALUE_AI, 'Begründung');
        $inv->setCurrentValue(null);

        $this->assertNull($inv->currentValue);
        $this->assertNull($inv->currentValueSource);
        $this->assertNull($inv->currentValueNote);
        $this->assertFalse($inv->isCurrentValueAiEstimate());
    }

    public function testFallsBackToTheArticleFixedValueAndOtherwiseStaysEmpty(): void
    {
        $this->assertSame(['value' => '15.00', 'origin' => CurrentValueResolver::ORIGIN_ARTICLE], CurrentValueResolver::resolve($this->inventoryNumber(), '15.00'));
        $this->assertSame(['value' => '15.00', 'origin' => CurrentValueResolver::ORIGIN_ARTICLE], CurrentValueResolver::resolve(null, '15.00'));
        $this->assertSame(['value' => null, 'origin' => null], CurrentValueResolver::resolve(null, null));
    }

    public function testNormalizesGermanAndEnglishAmounts(): void
    {
        $this->assertSame('1234.50', CurrentValueResolver::normalizeAmount('1.234,50 €'));
        $this->assertSame('12.50', CurrentValueResolver::normalizeAmount('12,5'));
        $this->assertSame('99.90', CurrentValueResolver::normalizeAmount('99.9'));
        $this->assertSame('0.00', CurrentValueResolver::normalizeAmount('0'));
        $this->assertNull(CurrentValueResolver::normalizeAmount('  '));
        $this->assertNull(CurrentValueResolver::normalizeAmount(null));
    }

    public function testRejectsInvalidAmounts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CurrentValueResolver::normalizeAmount('-5');
    }

    public function testFieldListIgnoresUnknownValuesAndKeepsCanonicalOrder(): void
    {
        $fields = InsuranceField::fromList(['purchase_price', 'unknown', 'category', 'category']);

        $this->assertSame([InsuranceField::Category, InsuranceField::PurchasePrice], $fields);
    }

    private function inventoryNumber(): InventoryNumber
    {
        $now = new DateTimeImmutable();

        return new InventoryNumber('inv-1', 'art-1', 'ABC-DEF-0001', null, null, null, null, null, null, $now, $now);
    }
}
