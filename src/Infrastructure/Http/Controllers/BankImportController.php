<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\BankImport\Command\BankImportRow;
use App\Application\BankImport\Command\CancelBankImport;
use App\Application\BankImport\Command\ConfirmBankImport;
use App\Application\BankImport\Command\ImportBankStatement;
use App\Application\Command\CommandBus;
use App\Application\Invoice\Query\GetInvoice;
use App\Application\Query\QueryBus;
use App\Application\Settings\Query\GetInvoiceSettings;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/**
 * Onlinebanking-Import (MT940/CAMT.053) zum Markieren von Rechnungen als
 * bezahlt ("Zahlungsabgleich"), siehe Projekt-Dokumentation. Lädt bei
 * Zuordnungstreffern zusätzlich die Anzeigedaten (Rechnungsnummer, Kunde,
 * Bruttosumme) über GetInvoice+GetInvoiceSettings nach — bewusst hier im
 * Controller statt im Application-Handler, da reine Anzeigedaten und
 * GetInvoice die Summenberechnung ohnehin bereits korrekt liefert.
 */
final class BankImportController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly InvoiceRepositoryInterface $invoices,
    ) {
    }

    public function index(Request $request): void
    {
        echo View::page('bank-import/index', [], activeNav: 'bank_import', title: t('bank_import.title'));
    }

    public function import(Request $request): void
    {
        $file = $request->file('file');

        if ($file === null) {
            Flash::error(t('bank_import.flash.no_file'));
            Response::redirect('/zahlungsabgleich');

            return;
        }

        try {
            $result = $this->commands->dispatch(new ImportBankStatement($file));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
            Response::redirect('/zahlungsabgleich');

            return;
        }

        $enrichedRows = array_map($this->enrichRow(...), $result->rows);
        $openInvoiceNumbers = array_values(array_filter(array_map(
            static fn ($invoice) => $invoice->invoiceNumber,
            $this->invoices->findSentUnpaid(),
        )));

        echo View::page('bank-import/review', [
            'importId' => $result->importId,
            'format' => $result->format,
            'rows' => $enrichedRows,
            'openInvoiceNumbers' => $openInvoiceNumbers,
        ], activeNav: 'bank_import', title: t('bank_import.title'));
    }

    public function confirm(Request $request): void
    {
        $importId = $request->input('import_id', '') ?? '';
        $included = $request->inputArray('included');
        $invoiceNumbers = $request->post['invoice_number'] ?? [];

        $selections = [];
        foreach ($included as $rowIndex) {
            $rowIndex = (int) $rowIndex;
            $invoiceNumber = isset($invoiceNumbers[$rowIndex]) ? trim((string) $invoiceNumbers[$rowIndex]) : '';
            if ($invoiceNumber !== '') {
                $selections[$rowIndex] = $invoiceNumber;
            }
        }

        try {
            $result = $this->commands->dispatch(new ConfirmBankImport($importId, $selections));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/zahlungsabgleich');

            return;
        }

        if ($result->markedPaidCount > 0) {
            Flash::success(t('bank_import.flash.confirmed', ['count' => (string) $result->markedPaidCount]));
        }
        if ($result->notFoundInvoiceNumbers !== []) {
            Flash::error(t('bank_import.flash.not_found', ['numbers' => implode(', ', $result->notFoundInvoiceNumbers)]));
        }
        if ($result->notPayableInvoiceNumbers !== []) {
            Flash::error(t('bank_import.flash.not_payable', ['numbers' => implode(', ', $result->notPayableInvoiceNumbers)]));
        }

        Response::redirect('/zahlungsabgleich');
    }

    public function cancel(Request $request): void
    {
        $importId = $request->input('import_id', '') ?? '';
        $this->commands->dispatch(new CancelBankImport($importId));
        Flash::success(t('bank_import.flash.cancelled'));
        Response::redirect('/zahlungsabgleich');
    }

    private function enrichRow(BankImportRow $row): BankImportRow
    {
        if ($row->matchedInvoiceNumber === null) {
            return $row;
        }

        $invoice = $this->invoices->findByInvoiceNumber($row->matchedInvoiceNumber);
        if ($invoice === null) {
            return $row;
        }

        $detail = $this->queries->ask(new GetInvoice($invoice->id));
        $invoiceSettings = $this->queries->ask(new GetInvoiceSettings());
        $totals = $detail->totals($invoiceSettings);
        $grossTotal = number_format($totals->grossTotal, 2, '.', '');

        return new BankImportRow(
            rowIndex: $row->rowIndex,
            bookingDate: $row->bookingDate,
            amount: $row->amount,
            currency: $row->currency,
            isCredit: $row->isCredit,
            purpose: $row->purpose,
            counterpartyName: $row->counterpartyName,
            reference: $row->reference,
            matchedInvoiceNumber: $row->matchedInvoiceNumber,
            matchedCustomerName: $detail->customer?->displayName(),
            matchedGrossTotal: $grossTotal,
            amountMismatch: abs(((float) $grossTotal) - ((float) $row->amount)) > 0.001,
        );
    }
}
