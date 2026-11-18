<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\Command\CommandBus;
use App\Application\Invoice\Command\CreateInvoiceFromRental;
use App\Application\Invoice\Query\GetInvoice;
use App\Application\MinuteProgram\Query\GetMinuteProgramDetail;
use App\Application\MinuteProgram\Query\ListMinuteProgramsForRental;
use App\Application\Query\QueryBus;
use App\Application\Rental\Command\AddRentalItem;
use App\Application\Rental\Command\CreateRental;
use App\Application\Rental\Command\DeleteRental;
use App\Application\Rental\Command\DeleteRentalItem;
use App\Application\Rental\Command\EmailPackingListPdf;
use App\Application\Rental\Command\UpdateRental;
use App\Application\Rental\Command\UpdateRentalItem;
use App\Application\Rental\Command\UpdateRentalLogistics;
use App\Application\Rental\Query\GetRental;
use App\Application\Rental\Query\ListRentals;
use App\Application\Rental\Query\ListRentalsInRange;
use App\Application\Rental\Query\RentalDetail;
use App\Application\Settings\Query\GetCompanySettings;
use App\Application\Settings\Query\GetPdfLayoutSettings;
use App\Application\Technician\Query\GetRentalTechnicians;
use App\Domain\Settings\PdfLayout\PdfDocumentType;
use App\Infrastructure\Http\Api\ApiController;
use App\Infrastructure\Http\Api\ApiException;
use App\Infrastructure\Http\Api\ApiRequest;
use App\Infrastructure\Http\Api\ApiResponse;
use App\Infrastructure\Http\Api\CommandFactory;
use App\Infrastructure\Http\Api\Normalizer;
use App\Infrastructure\Pdf\PackingListPdfGenerator;

/**
 * /api/v1/rentals (Veranstaltungen), /api/v1/rental-items, /api/v1/calendar —
 * scopes rentals:read / rentals:write. Issue/return of material stays in the
 * web UI (signature, ID check, accessory checks happen there).
 */
final class RentalApiController extends ApiController
{
    private const ALL = 1_000_000;

    public function __construct(
        QueryBus $queries,
        CommandBus $commands,
        private readonly PackingListPdfGenerator $packingListPdf,
    ) {
        parent::__construct($queries, $commands);
    }

    public function index(ApiRequest $request): ApiResponse
    {
        $result = $this->ask(new ListRentals(customerId: $request->query('customer_id'), page: 1, pageSize: self::ALL));
        $items = [];
        foreach ($result->items as $entry) {
            $items[] = Normalizer::normalize($entry->rental, ['termsSignatureFile']) + ['customer' => $this->customerRef($entry->customer)];
        }

        return $this->listResponse($request, $items);
    }

    public function show(ApiRequest $request): ApiResponse
    {
        return ApiResponse::data($this->detail($request->param('id')));
    }

    public function store(ApiRequest $request): ApiResponse
    {
        $id = $this->run(CreateRental::class, $request);

        return $this->created($this->detail((string) $id));
    }

    public function update(ApiRequest $request): ApiResponse
    {
        $id = $request->param('id');
        $detail = $this->load($id);
        $this->run(UpdateRental::class, $request, ['id' => $id], CommandFactory::props($detail->rental));

        return ApiResponse::data($this->detail($id));
    }

    public function destroy(ApiRequest $request): ApiResponse
    {
        $this->load($request->param('id'));
        $this->dispatch(new DeleteRental($request->param('id')));

        return ApiResponse::noContent();
    }

    /** PATCH /rentals/{id}/logistics: pickup/return warehouse, times, transport note. */
    public function updateLogistics(ApiRequest $request): ApiResponse
    {
        $id = $request->param('id');
        $detail = $this->load($id);
        $this->run(UpdateRentalLogistics::class, $request, ['rentalId' => $id], CommandFactory::props($detail->rental));

        return ApiResponse::data($this->detail($id));
    }

    public function addItem(ApiRequest $request): ApiResponse
    {
        $this->load($request->param('id'));
        $this->run(AddRentalItem::class, $request, ['rentalId' => $request->param('id')]);

        return $this->created($this->detail($request->param('id')));
    }

    public function updateItem(ApiRequest $request): ApiResponse
    {
        $this->run(UpdateRentalItem::class, $request, ['id' => $request->param('id')]);

        return ApiResponse::noContent();
    }

    public function deleteItem(ApiRequest $request): ApiResponse
    {
        $this->dispatch(new DeleteRentalItem($request->param('id')));

        return ApiResponse::noContent();
    }

    public function technicians(ApiRequest $request): ApiResponse
    {
        $this->load($request->param('id'));
        $team = $this->ask(new GetRentalTechnicians($request->param('id')));
        $rows = array_map(static fn (array $row) => Normalizer::normalize($row['assignment']) + [
            'user' => Normalizer::normalize($row['user']),
            'trade_names' => $row['tradeNames'],
        ], $team->rows);

        return ApiResponse::json(['data' => $rows, 'meta' => ['trade_ids' => $team->rentalTradeIds]]);
    }

    /** GET /calendar?from=Y-m-d&to=Y-m-d — rentals overlapping the period. */
    public function calendar(ApiRequest $request): ApiResponse
    {
        $from = $this->dateQuery($request, 'from');
        $to = $this->dateQuery($request, 'to', $from->modify('+30 days'));
        if ($to < $from) {
            throw ApiException::badRequest('"to" darf nicht vor "from" liegen.');
        }
        $items = [];
        foreach ($this->ask(new ListRentalsInRange($from->format('Y-m-d'), $to->format('Y-m-d'))) as $entry) {
            $items[] = Normalizer::normalize($entry->rental, ['termsSignatureFile']) + [
                'customer' => $this->customerRef($entry->customer),
                'material' => $entry->materialLabels,
            ];
        }

        return $this->listResponse($request, $items, ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]);
    }

    public function packingListPdf(ApiRequest $request): ApiResponse
    {
        $detail = $this->load($request->param('id'));
        $pdf = $this->packingListPdf->generate(
            $detail,
            $this->ask(new GetCompanySettings()),
            $this->ask(new GetPdfLayoutSettings(PdfDocumentType::PackingList)),
        );

        return $this->pdfResponse($pdf, 'Packliste-' . ($detail->rental->eventName !== '' ? $detail->rental->eventName : $detail->rental->id) . '.pdf');
    }

    /** POST /rentals/{id}/packing-list/email {recipient_email, note} — scope email:send. */
    public function emailPackingList(ApiRequest $request): ApiResponse
    {
        $this->load($request->param('id'));
        $this->run(EmailPackingListPdf::class, $request, ['rentalId' => $request->param('id')]);

        return ApiResponse::json(['ok' => true]);
    }

    /** POST /rentals/{id}/invoice — creates a draft invoice (scope invoices:write). */
    public function createInvoice(ApiRequest $request): ApiResponse
    {
        $this->load($request->param('id'));
        $invoiceId = $this->dispatch(new CreateInvoiceFromRental($request->param('id')));
        $detail = $this->ask(new GetInvoice((string) $invoiceId));

        return $this->created(Normalizer::normalize($detail->invoice));
    }

    /** GET /rentals/{id}/minute-programs — scope minute_programs:read. */
    public function minutePrograms(ApiRequest $request): ApiResponse
    {
        $this->load($request->param('id'));

        return $this->listResponse($request, Normalizer::list($this->ask(new ListMinuteProgramsForRental($request->param('id')))));
    }

    /** GET /minute-programs/{id} — scope minute_programs:read. */
    public function minuteProgram(ApiRequest $request): ApiResponse
    {
        return ApiResponse::data(Normalizer::normalize($this->ask(new GetMinuteProgramDetail($request->param('id')))));
    }

    private function load(string $id): RentalDetail
    {
        return $this->ask(new GetRental($id));
    }

    /** @return array<string, mixed> */
    private function detail(string $id): array
    {
        $detail = $this->load($id);

        return Normalizer::normalize($detail->rental, ['termsSignatureFile']) + [
            'customer' => $this->customerRef($detail->customer),
            'items' => array_map(static fn ($entry) => Normalizer::normalize($entry->item) + [
                'article' => ['id' => $entry->article->id, 'name' => $entry->article->name],
                'issued_count' => $entry->issuedCount,
            ], $detail->items),
            'issued_items' => array_map(static fn ($entry) => Normalizer::normalize($entry->issuedItem) + [
                'inventory_number' => ['id' => $entry->inventoryNumber->id, 'number' => $entry->inventoryNumber->number],
                'article' => ['id' => $entry->article->id, 'name' => $entry->article->name],
            ], $detail->issuedItems),
        ];
    }

    /** @return array<string, mixed> */
    private function customerRef(object $customer): array
    {
        return [
            'id' => $customer->id,
            'customer_number' => $customer->customerNumber,
            'display_name' => $customer->displayName(),
        ];
    }
}
