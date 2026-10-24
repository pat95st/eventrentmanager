<?php

declare(strict_types=1);

namespace App\Application\BankImport\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\BankImport\BankStatementFormat;
use App\Domain\BankImport\BankStatementFormatDetector;
use App\Domain\BankImport\InvoiceNumberMatcher;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Infrastructure\BankImport\BankImportStaging;
use App\Infrastructure\BankImport\Camt053Parser;
use App\Infrastructure\BankImport\Mt940Parser;
use App\Shared\Uuid;

/**
 * Liest die hochgeladene Kontoauszugsdatei, erkennt das Format, parst die
 * Buchungen, ordnet jeder Gutschrift automatisch höchstens eine offene
 * Rechnung zu, legt die Zwischenablage an und liefert das Ergebnis für die
 * Review-Seite zurück. Die Kontoauszug-Datei selbst wird NICHT dauerhaft
 * gespeichert (Datensparsamkeit, siehe Projekt-Dokumentation) — nur die für
 * den Abgleich relevanten, extrahierten Felder landen in der Zwischenablage.
 */
final class ImportBankStatementHandler implements CommandHandlerInterface
{
    private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB

    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly BankImportStaging $staging,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ImportBankStatement);

        $file = $command->uploadedFile;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException(['file' => 'Datei-Upload fehlgeschlagen (Fehlercode ' . $file['error'] . ').']);
        }

        if ($file['size'] > self::MAX_SIZE_BYTES) {
            throw new ValidationException(['file' => 'Die Datei darf höchstens 10 MB groß sein.']);
        }

        $content = is_file($file['tmp_name']) ? file_get_contents($file['tmp_name']) : false;
        if ($content === false || $content === '') {
            throw new ValidationException(['file' => 'Die Datei konnte nicht gelesen werden.']);
        }

        $format = BankStatementFormatDetector::detect($content);
        if ($format === null) {
            throw new ValidationException(['file' => 'Das Dateiformat wurde nicht als MT940 oder CAMT.053 erkannt.']);
        }

        $parser = $format === BankStatementFormat::Mt940 ? new Mt940Parser() : new Camt053Parser();
        $transactions = $parser->parse($content);

        $openInvoices = $this->invoices->findSentUnpaid();
        $knownInvoiceNumbers = array_values(array_filter(array_map(
            static fn ($invoice) => $invoice->invoiceNumber,
            $openInvoices,
        )));

        $rows = [];
        $stagedRows = [];

        foreach ($transactions as $index => $transaction) {
            $matchedInvoiceNumber = null;

            if ($transaction->isCredit) {
                $haystack = trim(($transaction->purpose ?? '') . ' ' . ($transaction->reference ?? ''));
                $matchedInvoiceNumber = InvoiceNumberMatcher::findInvoiceNumberInText($haystack, $knownInvoiceNumbers);
            }

            $rows[] = new BankImportRow(
                rowIndex: $index,
                bookingDate: $transaction->bookingDate->format('Y-m-d'),
                amount: $transaction->amount,
                currency: $transaction->currency,
                isCredit: $transaction->isCredit,
                purpose: $transaction->purpose,
                counterpartyName: $transaction->counterpartyName,
                reference: $transaction->reference,
                matchedInvoiceNumber: $matchedInvoiceNumber,
                matchedCustomerName: null,
                matchedGrossTotal: null,
                amountMismatch: false,
            );

            $stagedRows[] = [
                'rowIndex' => $index,
                'bookingDate' => $transaction->bookingDate->format('Y-m-d'),
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'isCredit' => $transaction->isCredit,
                'purpose' => $transaction->purpose,
                'counterpartyName' => $transaction->counterpartyName,
                'reference' => $transaction->reference,
                'matchedInvoiceNumber' => $matchedInvoiceNumber,
            ];
        }

        $importId = Uuid::v4();
        $this->staging->store($importId, [
            'format' => $format->value,
            'rows' => $stagedRows,
        ]);

        return new BankImportResult($importId, $format, $rows);
    }
}
