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

            return new ConfirmBankImportResult($markedPaidCount, $notFound, $notPayable);
        } finally {
            $this->staging->discard($command->importId);
        }
    }
}
