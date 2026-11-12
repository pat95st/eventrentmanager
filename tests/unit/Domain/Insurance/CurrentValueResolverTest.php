<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Insurance;

use App\Domain\Insurance\CurrentValue;
use App\Domain\Insurance\CurrentValueResolver;
use App\Domain\Insurance\InsuranceField;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class CurrentValueResolverTest extends Unit
{
    public function testOwnValueOfTheInventoryNumberWinsOverTheArticleFixedValue(): void
    {
        $own = new CurrentValue('inv-1', '120.00', CurrentValue::SOURCE_MANUAL, null, new DateTimeImmutable());

        $this->assertSame(['value' => '120.00', 'origin' => CurrentValueResolver::ORIGIN_ITEM], CurrentValueResolver::resolve($own, '15.00'));
    }

    public function testAiEstimateIsMarkedAsSuch(): void
    {
        $own = new CurrentValue('inv-1', '80.00', CurrentValue::SOURCE_AI, 'Begründung', new DateTimeImmutable());

        $this->assertSame(CurrentValueResolver::ORIGIN_AI, CurrentValueResolver::resolve($own, null)['origin']);
    }

    public function testFallsBackToTheArticleFixedValueAndOtherwiseStaysEmpty(): void
    {
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
}
