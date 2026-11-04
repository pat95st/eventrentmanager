<?php

declare(strict_types=1);

namespace App\Application\Statistics\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Application\Reporting\Query\GetIncomeExpenseOverview;
use App\Application\Reporting\Query\GetIncomeExpenseOverviewHandler;
use App\Application\Statistics\StatisticKey;
use App\Application\Statistics\StatisticsReadModelInterface;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Offer\OfferStatus;
use DateTimeImmutable;

/** Baut die Dashboard-Statistiken (Diagrammdaten und Tabellen der Detailseiten). */
final class GetStatisticHandler implements QueryHandlerInterface
{
    private const MONTHS = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];

    public function __construct(
        private readonly StatisticsReadModelInterface $readModel,
        private readonly GetIncomeExpenseOverviewHandler $incomeExpense,
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
    ) {
    }

    public function handle(QueryInterface $query): StatisticChart
    {
        assert($query instanceof GetStatistic);
        $months = max(3, min(36, $query->months));

        return match ($query->key) {
            StatisticKey::Revenue => $this->revenue(),
            StatisticKey::Rentals => $this->rentals($months),
            StatisticKey::TopArticles => $this->topArticles($months, $query->detailed ? 25 : 7),
            StatisticKey::Stock => $this->stock($query->detailed),
            StatisticKey::Offers => $this->offers($months),
            StatisticKey::Inspections => $this->inspections($query->detailed),
        };
    }

    private function revenue(): StatisticChart
    {
        $year = (int) (new DateTimeImmutable('today'))->format('Y');
        $current = $this->incomeExpense->handle(new GetIncomeExpenseOverview($year));
        $previous = $this->incomeExpense->handle(new GetIncomeExpenseOverview($year - 1));
        $labels = self::MONTHS;
        $income = array_map(static fn ($m) => round($m->income, 2), $current->months);
        $expense = array_map(static fn ($m) => round($m->expense, 2), $current->months);
        $previousIncome = array_map(static fn ($m) => round($m->income, 2), $previous->months);

        $rows = [];
        foreach ($current->months as $i => $month) {
            $rows[] = [$labels[$i] . ' ' . $year, self::money($month->income), self::money($month->expense), self::money($month->income - $month->expense), self::money($previous->months[$i]->income)];
        }
        $totalIncome = array_sum($income);
        $totalExpense = array_sum($expense);
        $rows[] = ['Summe', self::money($totalIncome), self::money($totalExpense), self::money($totalIncome - $totalExpense), self::money(array_sum($previousIncome))];

        return new StatisticChart(
            key: StatisticKey::Revenue,
            type: 'bar',
            labels: $labels,
            datasets: [
                ['label' => 'Einnahmen ' . $year, 'data' => $income, 'color' => 0],
                ['label' => 'Ausgaben ' . $year, 'data' => $expense, 'color' => 3],
                ['label' => 'Einnahmen ' . ($year - 1), 'data' => $previousIncome, 'type' => 'line', 'color' => 5],
            ],
            headline: self::money($totalIncome - $totalExpense),
            headlineCaption: 'Überschuss ' . $year . ' bisher',
            columns: ['Monat', 'Einnahmen', 'Ausgaben', 'Überschuss', 'Einnahmen Vorjahr'],
            rows: $rows,
            currency: true,
        );
    }

    private function rentals(int $months): StatisticChart
    {
        $start = (new DateTimeImmutable('first day of this month'))->modify('-' . ($months - 1) . ' months');
        $end = (new DateTimeImmutable('first day of this month'))->modify('+3 months')->modify('last day of this month');
        $counts = $this->readModel->rentalsPerMonth($start, $end);

        $labels = [];
        $data = [];
        $rows = [];
        $links = [];
        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 month')) {
            $key = $cursor->format('Y-m');
            $label = self::MONTHS[(int) $cursor->format('n') - 1] . ' ' . $cursor->format('y');
            $labels[] = $label;
            $data[] = $counts[$key] ?? 0;
            $rows[] = [self::MONTHS[(int) $cursor->format('n') - 1] . ' ' . $cursor->format('Y'), (string) ($counts[$key] ?? 0)];
            $links[] = '/kalender?monat=' . $key;
        }
        $thisMonth = $counts[(new DateTimeImmutable('today'))->format('Y-m')] ?? 0;

        return new StatisticChart(
            key: StatisticKey::Rentals,
            type: 'bar',
            labels: $labels,
            datasets: [['label' => 'Veranstaltungen', 'data' => $data, 'color' => 0]],
            headline: (string) $thisMonth,
            headlineCaption: 'in diesem Monat',
            columns: ['Monat', 'Veranstaltungen'],
            rows: $rows,
            links: $links,
        );
    }

    private function topArticles(int $months, int $limit): StatisticChart
    {
        $to = new DateTimeImmutable('today');
        $from = $to->modify('-' . $months . ' months');
        $top = $this->readModel->topArticles($from, $to, $limit);
        $labels = [];
        $data = [];
        $rows = [];
        $links = [];
        foreach ($top as $entry) {
            $article = $this->articles->findById($entry['articleId']);
            $manufacturer = $article?->manufacturerId !== null ? $this->manufacturers->findById($article->manufacturerId) : null;
            $label = $article !== null ? ArticleDisplayName::of($article, $manufacturer) : 'Gelöschter Artikel';
            $labels[] = mb_strlen($label) > 32 ? mb_substr($label, 0, 31) . '…' : $label;
            $data[] = $entry['quantity'];
            $rows[] = [$label, (string) $entry['quantity'], (string) $entry['rentals']];
            $links[] = $article !== null ? '/artikel/' . $article->id : null;
        }

        return new StatisticChart(
            key: StatisticKey::TopArticles,
            type: 'barH',
            labels: $labels,
            datasets: [['label' => 'Vermietete Stück', 'data' => $data, 'color' => 1]],
            headline: $rows[0][0] ?? '–',
            headlineCaption: 'Spitzenreiter der letzten ' . $months . ' Monate',
            columns: ['Artikel', 'Stück', 'Veranstaltungen'],
            rows: $rows,
            links: $links,
        );
    }

    private function stock(bool $detailed): StatisticChart
    {
        $all = $this->readModel->stockByCategory();
        $total = array_sum(array_map(static fn ($e) => $e['count'], $all));
        $shown = $detailed ? $all : array_slice($all, 0, 7);
        $rest = $detailed ? 0 : array_sum(array_map(static fn ($e) => $e['count'], array_slice($all, 7)));
        $labels = array_map(static fn ($e) => $e['name'], $shown);
        $data = array_map(static fn ($e) => $e['count'], $shown);
        if ($rest > 0) {
            $labels[] = 'Sonstige';
            $data[] = $rest;
        }
        $rows = array_map(static fn ($e) => [$e['name'], (string) $e['count'], $total > 0 ? number_format($e['count'] / $total * 100, 1, ',', '') . ' %' : '–'], $all);
        $links = array_map(static fn ($e) => $e['categoryId'] !== null ? '/artikel?kategorien=' . rawurlencode($e['categoryId']) : null, $all);

        return new StatisticChart(
            key: StatisticKey::Stock,
            type: 'doughnut',
            labels: $labels,
            datasets: [['label' => 'Exemplare', 'data' => $data]],
            headline: number_format($total, 0, ',', '.'),
            headlineCaption: 'Exemplare im Bestand',
            columns: ['Kategorie', 'Exemplare', 'Anteil'],
            rows: $rows,
            links: $links,
        );
    }

    private function offers(int $months): StatisticChart
    {
        $counts = $this->readModel->offersByStatus((new DateTimeImmutable('today'))->modify('-' . $months . ' months'));
        $labels = [];
        $data = [];
        $rows = [];
        $links = [];
        foreach (OfferStatus::cases() as $status) {
            $labels[] = $status->label();
            $data[] = $counts[$status->value] ?? 0;
            $rows[] = [$status->label(), (string) ($counts[$status->value] ?? 0)];
            $links[] = '/angebote?status=' . $status->value;
        }
        $decided = ($counts['accepted'] ?? 0) + ($counts['declined'] ?? 0) + ($counts['expired'] ?? 0);
        $rate = $decided > 0 ? round(($counts['accepted'] ?? 0) / $decided * 100) . ' %' : '–';

        return new StatisticChart(
            key: StatisticKey::Offers,
            type: 'doughnut',
            labels: $labels,
            datasets: [['label' => 'Angebote', 'data' => $data, 'colors' => array_map(static fn (OfferStatus $s) => match ($s) {
                OfferStatus::Draft => 5,
                OfferStatus::Sent => 0,
                OfferStatus::Accepted => 1,
                OfferStatus::Declined => 3,
                OfferStatus::Expired => 2,
            }, OfferStatus::cases())]],
            headline: $rate,
            headlineCaption: 'Annahmequote (entschiedene Angebote)',
            columns: ['Status', 'Angebote'],
            rows: $rows,
            links: $links,
        );
    }

    private function inspections(bool $detailed): StatisticChart
    {
        $today = new DateTimeImmutable('today');
        $soon = $today->modify('+30 days');
        $groups = ['overdue' => [], 'failed' => [], 'soon' => [], 'never' => [], 'ok' => []];
        foreach ($this->readModel->inspectionDueDates() as $entry) {
            if ($entry['dueDate'] === null) {
                $groups['never'][] = $entry;
            } elseif ($entry['passed'] === false) {
                $groups['failed'][] = $entry;
            } elseif (new DateTimeImmutable($entry['dueDate']) < $today) {
                $groups['overdue'][] = $entry;
            } elseif (new DateTimeImmutable($entry['dueDate']) <= $soon) {
                $groups['soon'][] = $entry;
            } else {
                $groups['ok'][] = $entry;
            }
        }
        $names = ['overdue' => 'Überfällig', 'failed' => 'Nicht bestanden', 'soon' => 'Fällig in 30 Tagen', 'never' => 'Nie geprüft', 'ok' => 'Gültig'];
        $rows = [];
        $links = [];
        if ($detailed) {
            $articleNames = [];
            foreach (['overdue', 'failed', 'soon', 'never'] as $group) {
                foreach ($groups[$group] as $entry) {
                    if (!array_key_exists($entry['articleId'], $articleNames)) {
                        $article = $this->articles->findById($entry['articleId']);
                        $manufacturer = $article?->manufacturerId !== null ? $this->manufacturers->findById($article->manufacturerId) : null;
                        $articleNames[$entry['articleId']] = $article !== null ? ArticleDisplayName::of($article, $manufacturer) : '–';
                    }
                    $rows[] = [$entry['number'], $articleNames[$entry['articleId']], $names[$group], $entry['dueDate'] !== null && $group !== 'failed' ? (new DateTimeImmutable($entry['dueDate']))->format('d.m.Y') : '–'];
                    $links[] = '/inventurnummern/' . $entry['inventoryNumberId'] . '/elektropruefungen';
                }
            }
        }

        return new StatisticChart(
            key: StatisticKey::Inspections,
            type: 'doughnut',
            labels: array_values($names),
            datasets: [['label' => 'Exemplare', 'data' => array_values(array_map('count', $groups)), 'colors' => [3, 4, 2, 5, 1]]],
            headline: (string) (count($groups['overdue']) + count($groups['failed'])),
            headlineCaption: 'überfällig oder nicht bestanden',
            columns: ['Inventurnummer', 'Artikel', 'Stand', 'Fällig am'],
            rows: $rows,
            links: $links,
        );
    }

    private static function money(float $value): string
    {
        return number_format($value, 2, ',', '.') . ' €';
    }
}
