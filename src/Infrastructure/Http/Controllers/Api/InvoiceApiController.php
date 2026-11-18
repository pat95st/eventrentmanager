<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\Command\CommandBus;
use App\Application\Dunning\Command\EmailDunningNotice;
use App\Application\Dunning\Query\GetDunningNotice;
use App\Application\Dunning\Query\ListDunningNoticesForInvoice;
use App\Application\Invoice\Command\CreateInvoice;
use App\Application\Invoice\Command\DeleteInvoice;
use App\Application\Invoice\Command\EmailInvoicePdf;
use App\Application\Invoice\Command\MarkInvoiceCancelled;
use App\Application\Invoice\Command\MarkInvoicePaid;
use App\Application\Invoice\Command\MarkInvoiceSent;
use App\Application\Invoice\Command\SendInvoiceSentViaMessaging;
use App\Application\Invoice\Command\UpdateInvoice;
use App\Application\Invoice\Query\GetInvoice;
use App\Application\Invoice\Query\InvoiceDetail;
use App\Application\Invoice\Query\ListInvoices;
use App\Application\InvoiceCorrection\Command\EmailInvoiceCorrection;
use App\Application\InvoiceCorrection\Query\GetInvoiceCorrection;
use App\Application\InvoiceCorrection\Query\ListInvoiceCorrections;
use App\Application\Query\QueryBus;
use App\Application\Settings\Query\GetInvoiceSettings;
use App\Domain\Invoice\InvoiceStatus;
use App\Infrastructure\Http\Api\ApiController;
use App\Infrastructure\Http\Api\ApiException;
use App\Infrastructure\Http\Api\ApiRequest;
use App\Infrastructure\Http\Api\ApiResponse;
use App\Infrastructure\Http\Api\CommandFactory;
use App\Infrastructure\Http\Api\DocumentPositions;
use App\Infrastructure\Http\Api\DocumentRenderer;
use App\Infrastructure\Http\Api\Normalizer;
use Throwable;

/**
 * /api/v1/invoices, /api/v1/invoice-corrections, /api/v1/dunning-notices.
 *
 * Scopes: invoices:read; invoices:write for drafts and "paid";
 * invoices:finalize for finalizing (assigns the invoice number, irreversible)
 * and cancelling; email:send for sending documents to the customer.
 */
final class InvoiceApiController extends ApiController
{
    public function __construct(
        QueryBus $queries,
        CommandBus $commands,
        private readonly DocumentRenderer $documents,
    ) {
        parent::__construct($queries, $commands);
    }

    public function index(ApiRequest $request): ApiResponse
    {
        $status = $request->query('status');
        $statusEnum = $status !== null && $status !== '' ? (InvoiceStatus::tryFrom($status) ?? throw ApiException::badRequest('Unbekannter Status "' . $status . '".')) : null;
        $result = $this->ask(new ListInvoices(status: $statusEnum, page: 1, pageSize: 1_000_000));
        $items = [];
        foreach ($result->items as $entry) {
            $items[] = Normalizer::normalize($entry->invoice) + ['customer' => SalesDocumentSupport::customerRef($entry->customer)];
        }

        return $this->listResponse($request, $items);
    }

    public function show(ApiRequest $request): ApiResponse
    {
        return ApiResponse::data($this->detail($request->param('id')));
    }

    /** Creates a draft (no invoice number yet). */
    public function store(ApiRequest $request): ApiResponse
    {
        $id = $this->run(CreateInvoice::class, $request);

        return $this->created($this->detail((string) $id));
    }

    /** Changes a draft; finalized invoices are rejected by the handler (409). */
    public function update(ApiRequest $request): ApiResponse
    {
        $id = $request->param('id');
        $detail = $this->load($id);
        $invoice = $detail->invoice;
        $current = CommandFactory::props($invoice) + DocumentPositions::rows($detail) + [
            'rentalId' => $invoice->sourceRentalId,
            'note' => $invoice->note ?? '',
            'eventLocation' => $invoice->eventLocation ?? '',
            'buyerReference' => $invoice->buyerReference ?? '',
            'logisticsDate1' => $invoice->logisticsDate1?->format('Y-m-d'),
            'logisticsDate2' => $invoice->logisticsDate2?->format('Y-m-d'),
        ];
        $this->run(UpdateInvoice::class, $request, ['id' => $id], $current);

        return ApiResponse::data($this->detail($id));
    }

    public function destroy(ApiRequest $request): ApiResponse
    {
        $this->load($request->param('id'));
        $this->dispatch(new DeleteInvoice($request->param('id')));

        return ApiResponse::noContent();
    }

    /** POST /invoices/{id}/finalize — assigns the final number (scope invoices:finalize). */
    public function finalize(ApiRequest $request): ApiResponse
    {
        return $this->transition($request, MarkInvoiceSent::class);
    }

    /** POST /invoices/{id}/cancel — scope invoices:finalize. */
    public function cancel(ApiRequest $request): ApiResponse
    {
        return $this->transition($request, MarkInvoiceCancelled::class);
    }

    /** POST /invoices/{id}/mark-paid — scope invoices:write. */
    public function markPaid(ApiRequest $request): ApiResponse
    {
        return $this->transition($request, MarkInvoicePaid::class);
    }

    public function pdf(ApiRequest $request): ApiResponse
    {
        [$name, $pdf] = $this->documents->invoicePdf($this->load($request->param('id')));

        return $this->pdfResponse($pdf, $name);
    }

    public function xrechnung(ApiRequest $request): ApiResponse
    {
        [$name, $xml] = $this->documents->invoiceXRechnung($this->load($request->param('id')));

        return ApiResponse::content($xml, 'application/xml', $name);
    }

    /** POST /invoices/{id}/email {recipient_email, note} — scope email:send. */
    public function email(ApiRequest $request): ApiResponse
    {
        $id = $request->param('id');
        $this->load($id);
        $this->run(EmailInvoicePdf::class, $request, ['invoiceId' => $id]);
        try {
            // Like the web UI: additionally notify via messenger, failures are ignored.
            $this->dispatch(new SendInvoiceSentViaMessaging($id));
        } catch (Throwable) {
        }

        return ApiResponse::json(['ok' => true]);
    }

    // --- corrections & dunning notices (read + PDF/e-mail) -----------------

    public function corrections(ApiRequest $request): ApiResponse
    {
        $entries = $this->ask(new ListInvoiceCorrections(invoiceId: $request->query('invoice_id')));

        return $this->listResponse($request, array_map(static fn ($entry) => Normalizer::normalize($entry->correction) + [
            'invoice_number' => $entry->invoiceNumber,
            'customer_name' => $entry->customerName,
        ], $entries));
    }

    public function correction(ApiRequest $request): ApiResponse
    {
        $detail = $this->ask(new GetInvoiceCorrection($request->param('id')));

        return ApiResponse::data(Normalizer::normalize($detail->correction) + [
            'invoice_number' => $detail->invoice->invoice->invoiceNumber,
            'invoice_net_total' => $detail->invoiceNetTotal,
            'invoice_gross_total' => $detail->invoiceGrossTotal,
        ]);
    }

    public function correctionPdf(ApiRequest $request): ApiResponse
    {
        [$name, $pdf] = $this->documents->correctionPdf($this->ask(new GetInvoiceCorrection($request->param('id'))));

        return $this->pdfResponse($pdf, $name);
    }

    public function correctionXRechnung(ApiRequest $request): ApiResponse
    {
        [$name, $xml] = $this->documents->correctionXRechnung($this->ask(new GetInvoiceCorrection($request->param('id'))));

        return ApiResponse::content($xml, 'application/xml', $name);
    }

    /** POST /invoice-corrections/{id}/email — scope email:send. */
    public function emailCorrection(ApiRequest $request): ApiResponse
    {
        $this->ask(new GetInvoiceCorrection($request->param('id')));
        $this->run(EmailInvoiceCorrection::class, $request, ['id' => $request->param('id')]);

        return ApiResponse::json(['ok' => true]);
    }

    public function dunningNotices(ApiRequest $request): ApiResponse
    {
        $this->load($request->param('id'));

        return $this->listResponse($request, Normalizer::list($this->ask(new ListDunningNoticesForInvoice($request->param('id')))));
    }

    public function dunningNotice(ApiRequest $request): ApiResponse
    {
        $detail = $this->ask(new GetDunningNotice($request->param('id')));

        return ApiResponse::data(Normalizer::normalize($detail->notice) + [
            'invoice_number' => $detail->invoice->invoiceNumber,
            'customer' => SalesDocumentSupport::customerRef($detail->customer),
        ]);
    }

    public function dunningNoticePdf(ApiRequest $request): ApiResponse
    {
        [$name, $pdf] = $this->documents->dunningPdf($this->ask(new GetDunningNotice($request->param('id'))));

        return $this->pdfResponse($pdf, $name);
    }

    /** POST /dunning-notices/{id}/email {recipient_email} — scope email:send. */
    public function emailDunningNotice(ApiRequest $request): ApiResponse
    {
        $this->ask(new GetDunningNotice($request->param('id')));
        $this->run(EmailDunningNotice::class, $request, ['dunningNoticeId' => $request->param('id')]);

        return ApiResponse::json(['ok' => true]);
    }

    /** @param class-string $command */
    private function transition(ApiRequest $request, string $command): ApiResponse
    {
        $this->load($request->param('id'));
        $this->dispatch(new $command($request->param('id')));

        return ApiResponse::data($this->detail($request->param('id')));
    }

    private function load(string $id): InvoiceDetail
    {
        return $this->ask(new GetInvoice($id));
    }

    /** @return array<string, mixed> */
    private function detail(string $id): array
    {
        $detail = $this->load($id);

        return Normalizer::normalize($detail->invoice) + [
            'customer' => SalesDocumentSupport::customerRef($detail->customer),
            'contact' => Normalizer::normalize($detail->contact),
            'totals' => Normalizer::normalize($detail->totals($this->ask(new GetInvoiceSettings()))),
        ] + DocumentPositions::forResponse($detail);
    }
}
