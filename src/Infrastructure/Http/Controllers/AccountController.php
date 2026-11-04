<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Banking\Command\AddAccountTransaction;
use App\Application\Banking\Command\BookInvoicePayment;
use App\Application\Banking\Command\BookReceiptPayment;
use App\Application\Banking\Command\DeleteAccountTransaction;
use App\Application\Banking\Command\SaveOpeningBalance;
use App\Application\Banking\Query\AccountPeriod;
use App\Application\Banking\Query\GetAccountLedger;
use App\Application\Command\CommandBus;
use App\Application\Invoice\Query\GetInvoice;
use App\Application\Query\QueryBus;
use App\Application\Settings\Query\GetInvoiceSettings;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Domain\Receipt\ReceiptStatus;
use App\Domain\Settings\BankAccountRepositoryInterface;
use App\Domain\Supplier\SupplierRepositoryInterface;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/**
 * Kontoführung (Migration 131): Saldoverlauf je Bankverbindung mit
 * Diagramm, Buchungsliste und Erfassung von Buchungen.
 */
final class AccountController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly BankAccountRepositoryInterface $accounts,
        private readonly ReceiptRepositoryInterface $receipts,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly ?SupplierRepositoryInterface $suppliers = null,
    ) {
    }

    public function index(Request $request): void
    {
        $account = $this->accounts->findDefault() ?? ($this->accounts->findAll()[0] ?? null);
        if ($account === null) {
            echo View::page('accounts/empty', [], activeNav: 'accounts', title: 'Kontoführung');

            return;
        }
        Response::redirect('/konten/' . $account->id . $this->periodQuery($request));
    }

    public function show(Request $request): void
    {
        $period = AccountPeriod::fromQuery($request->query('zeitraum'), $request->query('datum'));
        try {
            $ledger = $this->queries->ask(new GetAccountLedger($request->param('id'), $period));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        echo View::page('accounts/show', [
            'ledger' => $ledger,
            'openReceipts' => $this->openReceipts(),
            'openInvoices' => $this->openInvoices(),
        ], activeNav: 'accounts', title: 'Kontoführung · ' . $ledger->account->label);
    }

    public function saveOpeningBalance(Request $request): void
    {
        $this->run($request, fn (string $id) => $this->commands->dispatch(new SaveOpeningBalance(
            $id, $request->input('amount', '') ?? '', $request->input('date', '') ?? '',
        )), 'Der Anfangsbestand wurde gespeichert.');
    }

    public function addTransaction(Request $request): void
    {
        $this->run($request, fn (string $id) => $this->commands->dispatch(new AddAccountTransaction(
            $id,
            $request->input('booking_date', '') ?? '',
            $request->input('direction', '') ?? '',
            $request->input('amount', '') ?? '',
            $request->input('description', '') ?? '',
            $request->input('counterparty', '') ?? '',
        )), 'Die Buchung wurde erfasst.');
    }

    public function bookReceipt(Request $request): void
    {
        $this->run($request, fn (string $id) => $this->commands->dispatch(new BookReceiptPayment(
            $id, $request->input('receipt_id', '') ?? '', $request->input('booking_date', '') ?? '',
        )), 'Der Beleg wurde abgebucht und als bezahlt markiert.');
    }

    public function bookInvoice(Request $request): void
    {
        $this->run($request, fn (string $id) => $this->commands->dispatch(new BookInvoicePayment(
            $id, $request->input('invoice_id', '') ?? '', $request->input('booking_date', '') ?? '', $request->input('amount', '') ?? '',
        )), 'Die Rechnungszahlung wurde verbucht und die Rechnung als bezahlt markiert.');
    }

    public function deleteTransaction(Request $request): void
    {
        try {
            $accountId = $this->commands->dispatch(new DeleteAccountTransaction($request->param('transactionId')));
            Flash::success('Die Buchung wurde gelöscht.');
            Response::redirect('/konten/' . $accountId . $this->periodQuery($request, true));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/konten');
        }
    }

    private function run(Request $request, callable $action, string $success): void
    {
        $id = $request->param('id');
        try {
            $action($id);
            Flash::success($success);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        }
        Response::redirect('/konten/' . $id . $this->periodQuery($request, true));
    }

    /** Zeitraum der Ansicht beibehalten (aus der URL bzw. versteckten Formularfeldern). */
    private function periodQuery(Request $request, bool $fromPost = false): string
    {
        $mode = $fromPost ? $request->input('zeitraum') : $request->query('zeitraum');
        $date = $fromPost ? $request->input('datum') : $request->query('datum');
        if (($mode === null || $mode === '') && ($date === null || $date === '')) {
            return '';
        }

        return '?' . http_build_query(AccountPeriod::fromQuery($mode, $date)->query());
    }

    /** @return list<array{id: string, label: string, amount: string}> */
    private function openReceipts(): array
    {
        $result = [];
        foreach ($this->receipts->search(null, null, ReceiptStatus::Open, null, 300, 0) as $receipt) {
            $supplier = $this->suppliers?->findById($receipt->supplierId)?->name ?? '';
            $result[] = [
                'id' => $receipt->id,
                'label' => $receipt->receiptDate->format('d.m.Y') . ' · ' . $supplier
                    . ($receipt->receiptNumber !== null && $receipt->receiptNumber !== '' ? ' · ' . $receipt->receiptNumber : '')
                    . ' · ' . number_format((float) $receipt->amount, 2, ',', '.') . ' €',
                'amount' => $receipt->amount,
            ];
        }

        return $result;
    }

    /** @return list<array{id: string, label: string, amount: string}> */
    private function openInvoices(): array
    {
        $result = [];
        $settings = null;
        foreach ($this->invoices->findSentUnpaid() as $invoice) {
            $gross = '';
            $customer = '';
            try {
                $detail = $this->queries->ask(new GetInvoice($invoice->id));
                $settings ??= $this->queries->ask(new GetInvoiceSettings());
                $gross = number_format($detail->totals($settings)->grossTotal, 2, ',', '');
                $customer = $detail->customer?->displayName() ?? '';
            } catch (\Throwable) {
            }
            $result[] = [
                'id' => $invoice->id,
                'label' => ($invoice->invoiceNumber ?? '–') . ($customer !== '' ? ' · ' . $customer : '') . ($gross !== '' ? ' · ' . $gross . ' €' : ''),
                'amount' => $gross,
            ];
        }

        return $result;
    }
}
