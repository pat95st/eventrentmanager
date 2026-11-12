<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Datev;

use App\Domain\Datev\DatevAccountRole;
use App\Domain\Datev\DatevChartOfAccounts;
use App\Domain\Datev\DatevDebtorMode;
use App\Domain\Datev\DatevExportInterval;
use App\Domain\Datev\DatevSettings;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class DatevSettingsTest extends Unit
{
    public function testUsesChartDefaultsUnlessAnAccountIsConfigured(): void
    {
        $settings = new DatevSettings(chartOfAccounts: DatevChartOfAccounts::Skr04, accounts: ['revenue_7' => 4310]);

        $this->assertSame(4400, $settings->account(DatevAccountRole::Revenue19));
        $this->assertSame(4310, $settings->account(DatevAccountRole::Revenue7));
        $this->assertSame(4185, $settings->account(DatevAccountRole::RevenueSmallBusiness));
    }

    public function testPadsDefaultAccountsAndPersonalAccountsToTheAccountLength(): void
    {
        $settings = new DatevSettings(accountLength: 5);

        $this->assertSame(84000, $settings->account(DatevAccountRole::Revenue19));
        $this->assertSame(100000, $settings->effectiveDebtorAccount());
        $this->assertSame(700000, $settings->effectiveCreditorAccount());
    }

    public function testDerivesIndividualDebtorAccountsFromTheCustomerNumber(): void
    {
        $settings = new DatevSettings(debtorMode: DatevDebtorMode::Individual);

        $this->assertSame(10042, $settings->debtorAccountFor('K-00042'));
        $this->assertNull($settings->debtorAccountFor('K-99999'), 'outside the debtor range (>= 70000)');
        $this->assertNull($settings->debtorAccountFor(null));
    }

    public function testCollectiveModeAlwaysUsesTheCollectiveAccount(): void
    {
        $settings = new DatevSettings(debtorAccount: 12000);

        $this->assertSame(12000, $settings->debtorAccountFor('K-00042'));
        $this->assertSame(12000, $settings->debtorAccountFor(null));
    }

    public function testSplitsAPeriodAtTheFiscalYearBoundary(): void
    {
        $settings = new DatevSettings(fiscalYearStartMonth: 7);

        $chunks = $settings->splitByFiscalYear(new DateTimeImmutable('2026-05-01'), new DateTimeImmutable('2026-08-31'));

        $this->assertCount(2, $chunks);
        $this->assertSame('2025-07-01', $chunks[0]['fiscalYearStart']->format('Y-m-d'));
        $this->assertSame('2026-06-30', $chunks[0]['to']->format('Y-m-d'));
        $this->assertSame('2026-07-01', $chunks[1]['fiscalYearStart']->format('Y-m-d'));
        $this->assertSame('2026-07-01', $chunks[1]['from']->format('Y-m-d'));
        $this->assertSame('2026-08-31', $chunks[1]['to']->format('Y-m-d'));
    }

    public function testPreviousPeriodForMonthlyAndQuarterlyIntervals(): void
    {
        $today = new DateTimeImmutable('2026-11-12');

        [$from, $to] = DatevExportInterval::Monthly->previousPeriod($today);
        $this->assertSame(['2026-10-01', '2026-10-31'], [$from->format('Y-m-d'), $to->format('Y-m-d')]);

        [$from, $to] = DatevExportInterval::Quarterly->previousPeriod($today);
        $this->assertSame(['2026-07-01', '2026-09-30'], [$from->format('Y-m-d'), $to->format('Y-m-d')]);

        [$from, $to] = DatevExportInterval::Quarterly->previousPeriod(new DateTimeImmutable('2026-01-03'));
        $this->assertSame(['2025-10-01', '2025-12-31'], [$from->format('Y-m-d'), $to->format('Y-m-d')]);

        [$from, $to] = DatevExportInterval::Monthly->previousPeriod(new DateTimeImmutable('2026-03-31'));
        $this->assertSame(['2026-02-01', '2026-02-28'], [$from->format('Y-m-d'), $to->format('Y-m-d')]);
    }
}
