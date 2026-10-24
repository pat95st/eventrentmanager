<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Settings;

use App\Domain\Settings\PriceTier;
use App\Domain\Settings\RentalPriceCalculator;
use Codeception\Test\Unit;

final class RentalPriceCalculatorTest extends Unit
{
    public function testCalculatesThePriceForEachTierFromTheDailyRate(): void
    {
        $tiers = [
            new PriceTier(1, '100.00'),
            new PriceTier(2, '150.00'),
            new PriceTier(3, '175.00'),
        ];

        $prices = RentalPriceCalculator::calculate('20.00', $tiers);

        $this->assertSame([
            1 => '20.00',
            2 => '30.00',
            3 => '35.00',
        ], $prices);
    }

    public function testResultIsSortedByDayCountRegardlessOfInputOrder(): void
    {
        $tiers = [
            new PriceTier(3, '175.00'),
            new PriceTier(1, '100.00'),
            new PriceTier(2, '150.00'),
        ];

        $prices = RentalPriceCalculator::calculate('10.00', $tiers);

        $this->assertSame([1, 2, 3], array_keys($prices));
    }

    public function testAlwaysFormatsWithExactlyTwoDecimalPlaces(): void
    {
        $prices = RentalPriceCalculator::calculate('19.999', [new PriceTier(1, '100.00')]);

        $this->assertSame('20.00', $prices[1]);
    }

    public function testAnEmptyTierListYieldsAnEmptyResult(): void
    {
        $this->assertSame([], RentalPriceCalculator::calculate('20.00', []));
    }
}
