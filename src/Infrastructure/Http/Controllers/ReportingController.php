<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Query\QueryBus;
use App\Application\Reporting\Query\GetIncomeExpenseOverview;
use App\Application\Reporting\Query\GetRevenueForecast;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/** Berichte für die Geschäftsführung — Einnahmen-/Ausgabenübersicht (siehe GetIncomeExpenseOverviewHandler) und Umsatzprognose (siehe GetRevenueForecastHandler), Permission::Administration (siehe bootstrap.php). */
final class ReportingController
{
    private const FORECAST_YEAR_COUNT = 5;

    public function __construct(private readonly QueryBus $queries)
    {
    }

    public function incomeExpense(Request $request): void
    {
        $currentYear = (int) date('Y');
        $requestedYear = $request->input('jahr');
        $year = $requestedYear !== null && ctype_digit($requestedYear) ? (int) $requestedYear : $currentYear;

        // Vernünftige Grenzen gegen Tippfehler in der URL (z. B. ?jahr=abc
        // wurde bereits oben abgefangen, hier nur noch Plausibilität).
        if ($year < 2000 || $year > $currentYear + 1) {
            $year = $currentYear;
        }

        $overview = $this->queries->ask(new GetIncomeExpenseOverview($year));

        echo View::page('reporting/income-expense', [
            'overview' => $overview,
            'currentYear' => $currentYear,
        ], activeNav: 'reports', title: t('reporting.income_expense.title'));
    }

    public function revenueForecast(Request $request): void
    {
        $currentYear = (int) date('Y');
        $requestedYear = $request->input('ab');
        $fromYear = $requestedYear !== null && ctype_digit($requestedYear) ? (int) $requestedYear : $currentYear;

        // Vernünftige Grenzen gegen Tippfehler in der URL, analog incomeExpense().
        if ($fromYear < 2000 || $fromYear > $currentYear + 10) {
            $fromYear = $currentYear;
        }

        $forecast = $this->queries->ask(new GetRevenueForecast($fromYear, self::FORECAST_YEAR_COUNT));

        echo View::page('reporting/revenue-forecast', [
            'forecast' => $forecast,
            'currentYear' => $currentYear,
            'fromYear' => $fromYear,
        ], activeNav: 'revenue-forecast', title: t('reporting.revenue_forecast.title'));
    }
}
