<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Depreciation;

use App\Domain\Depreciation\DepreciationCalculator;
use App\Domain\Depreciation\DepreciationFirstYearMode;
use App\Domain\Depreciation\DepreciationSettings;
use App\Domain\Depreciation\UsefulLifeResolver;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class DepreciationCalculatorTest extends Unit
{
    public function testLinearDepreciationIsProRataInTheAcquisitionYear(): void
    {
        // 1.200 € über 5 Jahre = 20 €/Monat; angeschafft im Juli → 6 Monate im ersten Jahr.
        $schedule = DepreciationCalculator::schedule('1200.00', new DateTimeImmutable('2024-07-15'), 5, $this->settings());

        $this->assertFalse($schedule->lowValueAsset);
        $this->assertSame([2024, 2025, 2026, 2027, 2028, 2029], array_keys($schedule->years));
        $this->assertSame(120.0, $schedule->amountIn(2024));
        $this->assertSame(240.0, $schedule->amountIn(2025));
        $this->assertSame(120.0, $schedule->amountIn(2029));
        $this->assertSame(1080.0, $schedule->bookValueAtEndOf(2024));
        $this->assertSame(1200.0, $schedule->bookValueAtStartOf(2024));
        $this->assertSame(1080.0, $schedule->bookValueAtStartOf(2025));
        $this->assertSame(0.0, $schedule->bookValueAtEndOf(2029));
        $this->assertSame(0.0, $schedule->bookValueAtEndOf(2035));
        $this->assertSame(0.0, $schedule->amountIn(2030));
        $this->assertSame(0.0, $schedule->bookValueAtEndOf(2023));
        $this->assertSame(1200.0, $schedule->accumulatedUntil(2040));
    }

    public function testFullYearModeTakesTheFullRateInTheAcquisitionYear(): void
    {
        $schedule = DepreciationCalculator::schedule('1200.00', new DateTimeImmutable('2024-11-01'), 4, $this->settings(mode: DepreciationFirstYearMode::FullYear));

        $this->assertSame([2024, 2025, 2026, 2027], array_keys($schedule->years));
        $this->assertSame(300.0, $schedule->amountIn(2024));
        $this->assertSame(0.0, $schedule->bookValueAtEndOf(2027));
    }

    public function testYearlyAmountsAlwaysAddUpToTheCostDespiteRounding(): void
    {
        $schedule = DepreciationCalculator::schedule('1000.00', new DateTimeImmutable('2023-03-10'), 3, $this->settings());

        $sum = 0.0;
        foreach ($schedule->years as $year) {
            $sum += $year->amount;
        }
        $this->assertSame(1000.0, round($sum, 2));
        $this->assertSame(277.78, $schedule->amountIn(2023));
        $this->assertSame(0.0, $schedule->bookValueAtEndOf($schedule->lastYear()));
    }

    public function testLowValueAssetIsWrittenOffInTheAcquisitionYear(): void
    {
        $schedule = DepreciationCalculator::schedule('800.00', new DateTimeImmutable('2025-12-20'), 5, $this->settings());

        $this->assertTrue($schedule->lowValueAsset);
        $this->assertSame([2025], array_keys($schedule->years));
        $this->assertSame(800.0, $schedule->amountIn(2025));
        $this->assertSame(0.0, $schedule->bookValueAtEndOf(2025));
    }

    public function testThresholdOfZeroDisablesTheImmediateWriteOff(): void
    {
        $schedule = DepreciationCalculator::schedule('100.00', new DateTimeImmutable('2025-01-01'), 2, $this->settings(threshold: '0.00'));

        $this->assertFalse($schedule->lowValueAsset);
        $this->assertSame(50.0, $schedule->amountIn(2025));
        $this->assertSame(50.0, $schedule->amountIn(2026));
    }

    public function testResidualValueRemainsAfterTheUsefulLife(): void
    {
        $schedule = DepreciationCalculator::schedule('1201.00', new DateTimeImmutable('2024-01-01'), 5, $this->settings(residual: '1.00'));

        $this->assertSame(240.0, $schedule->amountIn(2024));
        $this->assertSame(2028, $schedule->lastYear());
        $this->assertSame(1.0, $schedule->bookValueAtEndOf(2028));
        $this->assertSame(1.0, $schedule->bookValueAtEndOf(2040));
    }

    public function testSaleStopsDepreciationAndWritesOffTheRemainingBookValue(): void
    {
        // Verkauf im April 2026: 2024 = 6 Monate, 2025 = 12, 2026 = 3 (Jan–März) → 420 € abgeschrieben.
        $schedule = DepreciationCalculator::schedule('1200.00', new DateTimeImmutable('2024-07-15'), 5, $this->settings(), new DateTimeImmutable('2026-04-10 14:00:00'));

        $this->assertSame([2024, 2025, 2026], array_keys($schedule->years));
        $this->assertSame(60.0, $schedule->amountIn(2026));
        $this->assertSame(780.0, $schedule->disposalIn(2026));
        $this->assertSame(0.0, $schedule->bookValueAtEndOf(2026));
        $this->assertSame(0.0, $schedule->bookValueAtEndOf(2030));
        $this->assertTrue($schedule->coversYear(2026));
        $this->assertFalse($schedule->coversYear(2027));
        $this->assertFalse($schedule->coversYear(2023));
        $this->assertSame(420.0, $schedule->accumulatedUntil(2030));
    }

    public function testSaleAfterTheUsefulLifeLeavesNothingToWriteOff(): void
    {
        $schedule = DepreciationCalculator::schedule('1200.00', new DateTimeImmutable('2020-01-01'), 2, $this->settings(), new DateTimeImmutable('2025-06-01'));

        $this->assertSame(0.0, $schedule->disposalIn(2025));
        $this->assertSame(0.0, $schedule->amountIn(2025));
        $this->assertSame(0.0, $schedule->bookValueAtEndOf(2024));
        $this->assertTrue($schedule->coversYear(2025));
    }

    public function testSaleInTheAcquisitionMonth(): void
    {
        $schedule = DepreciationCalculator::schedule('1200.00', new DateTimeImmutable('2025-05-02'), 5, $this->settings(), new DateTimeImmutable('2025-05-20'));

        $this->assertSame([2025], array_keys($schedule->years));
        $this->assertSame(0.0, $schedule->amountIn(2025));
        $this->assertSame(1200.0, $schedule->disposalIn(2025));
    }

    public function testUsefulLifeIsInheritedFromTheNearestCategoryWithAValue(): void
    {
        $resolver = new UsefulLifeResolver(
            ['licht' => null, 'moving-heads' => 'licht', 'spots' => 'moving-heads', 'ton' => null, 'a' => 'b', 'b' => 'a'],
            ['licht' => 7, 'spots' => 3],
            5,
        );

        $this->assertSame(3, $resolver->forCategory('spots'));
        $this->assertSame(7, $resolver->forCategory('moving-heads'));
        $this->assertSame(5, $resolver->forCategory('ton'));
        $this->assertSame(5, $resolver->forCategory(null));
        $this->assertSame(5, $resolver->forCategory('unbekannt'));
        $this->assertSame(5, $resolver->forCategory('a')); // Zyklus bricht ab
    }

    private function settings(
        string $threshold = '800.00',
        DepreciationFirstYearMode $mode = DepreciationFirstYearMode::Monthly,
        string $residual = '0.00',
    ): DepreciationSettings {
        return new DepreciationSettings(5, $threshold, $mode, $residual);
    }
}
