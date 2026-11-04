<?php

declare(strict_types=1);

namespace App\Application\Banking\Query;

use App\Domain\Banking\AccountTransaction;
use App\Domain\Settings\BankAccount;
use DateTimeImmutable;

/** Ergebnis von GetAccountLedger: Saldo, Buchungen und Diagrammpunkte eines Zeitraums. */
final class AccountLedger
{
    /**
     * @param BankAccount[] $accounts alle Konten (für die Auswahl)
     * @param list<array{transaction: AccountTransaction, balanceCents: int|null}> $rows Buchungen im Zeitraum mit Saldo danach
     * @param list<string> $chartLabels
     * @param list<float|null> $chartValues Saldo in Euro, null = keine Daten (vor Anfangsbestand / Zukunft)
     */
    public function __construct(
        public readonly BankAccount $account,
        public readonly array $accounts,
        public readonly AccountPeriod $period,
        public readonly int $openingCents,
        public readonly ?DateTimeImmutable $openingDate,
        public readonly ?int $startBalanceCents,
        public readonly ?int $endBalanceCents,
        public readonly int $incomeCents,
        public readonly int $expenseCents,
        public readonly array $rows,
        public readonly array $chartLabels,
        public readonly array $chartValues,
        public readonly ?int $currentBalanceCents,
    ) {
    }
}
