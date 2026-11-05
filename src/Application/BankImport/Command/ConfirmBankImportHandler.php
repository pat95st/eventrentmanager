<?php

declare(strict_types=1);

namespace App\Application\BankImport\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Infrastructure\BankImport\BankImportStaging;
use DateTimeImmutable;

/**
 * Markiert je ausgewählter Zeile die (ggf. manuell korrigierte) Rechnung
 * als bezahlt. Dupliziert bewusst die kleine Statusübergang-Logik direkt
 * über InvoiceRepositoryInterface, statt den bestehenden MarkInvoicePaid-
 * Command per CommandBus erneut zu dispatchen — exakt nach dem bereits
 * etablierten Projekt-Vorbild ReturnRentalItemHandler (siehe
 * Projekt-Dokumentation), da im gesamten Projekt kein Application-Handler
 * selbst CommandBus/QueryBus injiziert bekommt. $paidAt wird auf das
 * Buchungsdatum der Bank gesetzt, nicht auf den Zeitpunkt des Imports.
 * Verwirft die Zwischenablage am Ende IMMER, auch wenn einzelne Zeilen
 * nicht zahlbar waren.
 */
final class ConfirmBankImportHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly BankImportStaging $staging,
        // Kontoführung (Migration 131)
        private readonly ?\App\Domain\Banking\AccountLedgerRepositoryInterface $ledger = null,
        private readonly ?\App\Domain\Settings\BankAccountRepositoryInterface $bankAccounts = null,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ConfirmBankImport);

        $staged = $this->staging->load($command->importId);
        if ($staged === null) {
            throw new NotFoundException('Der Zahlungsabgleich wurde nicht gefunden (evtl. bereits bestätigt/abgebrochen oder abgelaufen).');
        }

        try {
            /** @var array<int, array<string, mixed>> $stagedRows */
            $stagedRows = $staged['rows'] ?? [];
            $rowsByIndex = [];
            foreach ($stagedRows as $row) {
                $rowsByIndex[(int) $row['rowIndex']] = $row;
            }

            $markedPaidCount = 0;
            $notFound = [];
            $notPayable = [];
            $invoiceIdByRow = [];

            foreach ($command->selections as $rowIndex => $invoiceNumberRaw) {
                $invoiceNumber = trim((string) $invoiceNumberRaw);
                if ($invoiceNumber === '' || !isset($rowsByIndex[(int) $rowIndex])) {
                    continue;
                }

                $row = $rowsByIndex[(int) $rowIndex];

                $invoice = $this->invoices->findByInvoiceNumber($invoiceNumber);
                if ($invoice === null) {
                    $notFound[] = $invoiceNumber;

                    continue;
                }
                $invoiceIdByRow[(int) $rowIndex] = $invoice->id;

                if ($invoice->status !== InvoiceStatus::Sent) {
                    $notPayable[] = $invoiceNumber;

                    continue;
                }

                $invoice->status = InvoiceStatus::Paid;
                $invoice->paidAt = new DateTimeImmutable((string) $row['bookingDate']);
                $invoice->updatedAt = new DateTimeImmutable();
                $this->invoices->save($invoice);

                $markedPaidCount++;
            }

            [$booked, $skipped] = $this->bookToLedger($command, $rowsByIndex, $invoiceIdByRow);

            return new ConfirmBankImportResult($markedPaidCount, $notFound, $notPayable, $booked, $skipped);
        } finally {
            $this->staging->discard($command->importId);
        }
    }

    /**
     * Umsätze in die Kontoführung übernehmen (Migration 131). Bereits
     * übernommene Umsätze (gleicher Fingerabdruck) werden übersprungen, so
     * dass derselbe Kontoauszug gefahrlos mehrfach importiert werden kann.
     *
     * @param array<int, array<string, mixed>> $rowsByIndex
     * @param array<int, string> $invoiceIdByRow
     * @return array{0: int, 1: int}
     */
    private function bookToLedger(ConfirmBankImport $command, array $rowsByIndex, array $invoiceIdByRow): array
    {
        if ($this->ledger === null || $command->ledgerAccountId === null || $command->ledgerAccountId === ''
            || ($this->bankAccounts !== null && !($this->bankAccounts->findById($command->ledgerAccountId)?->ledgerEnabled ?? false))) {
            return [0, 0];
        }
        $wanted = array_flip(array_map('intval', $command->ledgerRows));
        $occurrences = [];
        $booked = 0;
        $skipped = 0;
        ksort($rowsByIndex);
        foreach ($rowsByIndex as $rowIndex => $row) {
            $fingerprint = implode('|', [
                (string) $row['bookingDate'], (string) $row['amount'], !empty($row['isCredit']) ? 'C' : 'D',
                trim((string) ($row['purpose'] ?? '')), trim((string) ($row['counterpartyName'] ?? '')), trim((string) ($row['reference'] ?? '')),
            ]);
            // Gleiche Umsätze am selben Tag (z. B. zwei identische Gebühren) unterscheiden.
            $occurrences[$fingerprint] = ($occurrences[$fingerprint] ?? 0) + 1;
            if (!isset($wanted[$rowIndex])) {
                continue;
            }
            $hash = hash('sha256', $fingerprint . '|' . $occurrences[$fingerprint]);
            if ($this->ledger->importHashExists($command->ledgerAccountId, $hash)) {
                $skipped++;
                continue;
            }
            $cents = abs(\App\Domain\Banking\AccountAmount::fromDecimal((string) $row['amount']));
            $invoiceId = !empty($row['isCredit']) ? ($invoiceIdByRow[$rowIndex] ?? null) : null;
            if ($invoiceId !== null && $this->ledger->findByInvoice($invoiceId) !== []) {
                $invoiceId = null; // Zahlung ist bereits verbucht – nur als Umsatz übernehmen
            }
            $purpose = trim((string) ($row['purpose'] ?? ''));
            $counterparty = trim((string) ($row['counterpartyName'] ?? ''));
            $now = new DateTimeImmutable();
            $this->ledger->save(new \App\Domain\Banking\AccountTransaction(
                \App\Shared\Uuid::v4(),
                $command->ledgerAccountId,
                new DateTimeImmutable((string) $row['bookingDate']),
                !empty($row['isCredit']) ? $cents : -$cents,
                $invoiceId !== null ? \App\Domain\Banking\AccountTransactionKind::Invoice : \App\Domain\Banking\AccountTransactionKind::Import,
                $purpose !== '' ? $purpose : ($counterparty !== '' ? $counterparty : 'Umsatz laut Kontoauszug'),
                $counterparty !== '' ? $counterparty : null,
                null,
                $invoiceId,
                $hash,
                $now,
                $now,
            ));
            $booked++;
        }

        return [$booked, $skipped];
    }
}
