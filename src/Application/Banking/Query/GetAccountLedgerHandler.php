<?php

declare(strict_types=1);

namespace App\Application\Banking\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Banking\AccountLedgerRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Settings\BankAccountRepositoryInterface;
use DateTimeImmutable;

/**
 * Kontoverlauf für einen Zeitraum (Migration 131). Saldo zu einem Tag =
 * Anfangsbestand + alle Buchungen ab dem Datum des Anfangsbestands bis
 * einschließlich dieses Tages. Vor dem Datum des Anfangsbestands gibt es
 * keinen Saldo (Diagramm ohne Linie). Diagramm: Monat/Jahr tageweise,
 * mehrere Jahre mit einem Punkt je Monatsende; Tage nach heute bleiben leer.
 */
final class GetAccountLedgerHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly AccountLedgerRepositoryInterface $ledger,
        private readonly BankAccountRepositoryInterface $accounts,
    ) {
    }

    public function handle(QueryInterface $query): AccountLedger
    {
        assert($query instanceof GetAccountLedger);
        $account = $this->accounts->findById($query->bankAccountId);
        if ($account === null) {
            throw new NotFoundException('Das Konto wurde nicht gefunden.');
        }
        $period = $query->period;
        ['cents' => $opening, 'date' => $openingDate] = $this->ledger->openingBalance($account->id);

        $startBalance = ($openingDate === null || $openingDate <= $period->from)
            ? $opening + $this->ledger->sumBetween($account->id, $openingDate, $period->from)
            : null;

        $transactions = $this->ledger->findBetween($account->id, $period->from, $period->to);
        $byDay = [];
        $income = 0;
        $expense = 0;
        foreach ($transactions as $transaction) {
            $byDay[$transaction->bookingDate->format('Y-m-d')][] = $transaction;
            if ($transaction->amountCents >= 0) {
                $income += $transaction->amountCents;
            } else {
                $expense += -$transaction->amountCents;
            }
        }

        $today = new DateTimeImmutable('today');
        $balance = $startBalance;
        $rows = [];
        $daily = [];
        for ($day = $period->from; $day <= $period->to; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            if ($balance === null && $openingDate !== null && $key === $openingDate->format('Y-m-d')) {
                $balance = $opening;
            }
            foreach ($byDay[$key] ?? [] as $transaction) {
                $countsForBalance = $balance !== null && ($openingDate === null || $transaction->bookingDate >= $openingDate);
                if ($countsForBalance) {
                    $balance += $transaction->amountCents;
                }
                $rows[] = ['transaction' => $transaction, 'balanceCents' => $countsForBalance ? $balance : null];
            }
            $daily[$key] = $day > $today ? null : $balance;
        }

        [$labels, $values] = $this->chartPoints($period, $daily);
        $current = $today >= $period->from && $today <= $period->to ? ($daily[$today->format('Y-m-d')] ?? null) : null;

        return new AccountLedger(
            account: $account,
            accounts: array_values(array_filter($this->accounts->findAll(), static fn ($a) => $a->ledgerEnabled)),
            period: $period,
            openingCents: $opening,
            openingDate: $openingDate,
            startBalanceCents: $startBalance,
            endBalanceCents: $balance,
            incomeCents: $income,
            expenseCents: $expense,
            rows: $rows,
            chartLabels: $labels,
            chartValues: $values,
            currentBalanceCents: $current,
        );
    }

    /**
     * @param array<string, int|null> $daily
     * @return array{0: list<string>, 1: list<float|null>}
     */
    private function chartPoints(AccountPeriod $period, array $daily): array
    {
        $labels = [];
        $values = [];
        if ($period->mode === AccountPeriod::YEARS) {
            $today = (new DateTimeImmutable('today'))->format('Y-m-d');
            $lastValue = null;
            $currentMonth = null;
            foreach ($daily as $key => $value) {
                $month = substr($key, 0, 7);
                if ($currentMonth !== null && $month !== $currentMonth) {
                    $labels[] = substr($currentMonth, 5, 2) . '/' . substr($currentMonth, 0, 4);
                    $values[] = $lastValue;
                }
                $currentMonth = $month;
                $lastValue = $key <= $today ? ($value !== null ? $value / 100 : null) : $lastValue;
                if ($key > $today && substr($today, 0, 7) !== $month) {
                    $lastValue = null;
                }
            }
            if ($currentMonth !== null) {
                $labels[] = substr($currentMonth, 5, 2) . '/' . substr($currentMonth, 0, 4);
                $values[] = $lastValue;
            }

            return [$labels, $values];
        }
        foreach ($daily as $key => $value) {
            $labels[] = substr($key, 8, 2) . '.' . substr($key, 5, 2) . '.' . ($period->mode === AccountPeriod::YEAR ? '' : substr($key, 0, 4));
            $values[] = $value !== null ? $value / 100 : null;
        }

        return [$labels, $values];
    }
}
