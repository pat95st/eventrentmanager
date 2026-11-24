<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\Command\CommandBus;
use App\Application\Invoice\Command\CreateInvoiceFromOffer;
use App\Application\Invoice\Query\GetInvoice;
use App\Application\Offer\Command\ConvertOfferToRental;
use App\Application\Offer\Command\CreateOffer;
use App\Application\Offer\Command\DeleteOffer;
use App\Application\Offer\Command\EmailOfferPdf;
use App\Application\Offer\Command\MarkOfferAccepted;
use App\Application\Offer\Command\MarkOfferDeclined;
use App\Application\Offer\Command\MarkOfferSent;
use App\Application\Offer\Command\UpdateOffer;
use App\Application\Offer\Query\GetOffer;
use App\Application\Offer\Query\ListOffers;
use App\Application\Offer\Query\OfferDetail;
use App\Application\OrderConfirmation\Command\CreateOrderConfirmationFromOffer;
use App\Application\OrderConfirmation\Query\GetOrderConfirmation;
use App\Application\Query\QueryBus;
use App\Application\Rental\Query\GetRental;
use App\Domain\Offer\OfferStatus;
use App\Infrastructure\Http\Api\ApiController;
use App\Infrastructure\Http\Api\ApiException;
use App\Infrastructure\Http\Api\ApiRequest;
use App\Infrastructure\Http\Api\ApiResponse;
use App\Infrastructure\Http\Api\CommandFactory;
use App\Infrastructure\Http\Api\DocumentPositions;
use App\Infrastructure\Http\Api\DocumentRenderer;
use App\Infrastructure\Http\Api\Normalizer;

/**
 * /api/v1/offers — scopes sales:read / sales:write; e-mail needs email:send.
 * A shop can create an inquiry as offer draft via POST /offers.
 */
final class OfferApiController extends ApiController
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
        $statusEnum = $status !== null && $status !== '' ? (OfferStatus::tryFrom($status) ?? throw ApiException::badRequest('Unbekannter Status "' . $status . '".')) : null;
        $result = $this->ask(new ListOffers(status: $statusEnum, page: 1, pageSize: 1_000_000));
        $items = [];
        foreach ($result->items as $entry) {
            $items[] = Normalizer::normalize($entry->offer) + ['customer' => SalesDocumentSupport::customerRef($entry->customer)];
        }

        return $this->listResponse($request, $items);
    }

    public function show(ApiRequest $request): ApiResponse
    {
        return ApiResponse::data($this->detail($request->param('id')));
    }

    public function store(ApiRequest $request): ApiResponse
    {
        $id = $this->run(CreateOffer::class, $request, [], null, SalesDocumentSupport::POSITION_ALIASES);

        return $this->created($this->detail((string) $id));
    }

    public function update(ApiRequest $request): ApiResponse
    {
        $id = $request->param('id');
        $detail = $this->load($id);
        $current = CommandFactory::props($detail->offer) + DocumentPositions::rows($detail) + [
            'rentalId' => $detail->offer->sourceRentalId,
            'note' => $detail->offer->note ?? '',
            'eventLocation' => $detail->offer->eventLocation ?? '',
        ];
        $this->run(UpdateOffer::class, $request, ['id' => $id], $current, SalesDocumentSupport::POSITION_ALIASES);

        return ApiResponse::data($this->detail($id));
    }

    public function destroy(ApiRequest $request): ApiResponse
    {
        $this->load($request->param('id'));
        $this->dispatch(new DeleteOffer($request->param('id')));

        return ApiResponse::noContent();
    }

    public function markSent(ApiRequest $request): ApiResponse
    {
        return $this->transition($request, MarkOfferSent::class);
    }

    public function accept(ApiRequest $request): ApiResponse
    {
        return $this->transition($request, MarkOfferAccepted::class);
    }

    public function decline(ApiRequest $request): ApiResponse
    {
        return $this->transition($request, MarkOfferDeclined::class);
    }

    /** POST /offers/{id}/rental — scopes sales:write + rentals:write. */
    public function convertToRental(ApiRequest $request): ApiResponse
    {
        $this->load($request->param('id'));
        $rentalId = (string) $this->dispatch(new ConvertOfferToRental($request->param('id')));
        $rental = $this->ask(new GetRental($rentalId));

        return $this->created(Normalizer::normalize($rental->rental, ['termsSignatureFile', 'plannedTripsInitialized']));
    }

    /** POST /offers/{id}/order-confirmation */
    public function createOrderConfirmation(ApiRequest $request): ApiResponse
    {
        $this->load($request->param('id'));
        $id = (string) $this->dispatch(new CreateOrderConfirmationFromOffer($request->param('id')));

        return $this->created(Normalizer::normalize($this->ask(new GetOrderConfirmation($id))->orderConfirmation));
    }

    /** POST /offers/{id}/invoice — scopes sales:read + invoices:write. */
    public function createInvoice(ApiRequest $request): ApiResponse
    {
        $this->load($request->param('id'));
        $id = (string) $this->dispatch(new CreateInvoiceFromOffer($request->param('id')));

        return $this->created(Normalizer::normalize($this->ask(new GetInvoice($id))->invoice));
    }

    public function pdf(ApiRequest $request): ApiResponse
    {
        [$name, $pdf] = $this->documents->offerPdf($this->load($request->param('id')));

        return $this->pdfResponse($pdf, $name);
    }

    /** POST /offers/{id}/email {recipient_email, note} — scope email:send. */
    public function email(ApiRequest $request): ApiResponse
    {
        $this->load($request->param('id'));
        $this->run(EmailOfferPdf::class, $request, ['offerId' => $request->param('id')]);

        return ApiResponse::json(['ok' => true]);
    }

    /** @param class-string $command */
    private function transition(ApiRequest $request, string $command): ApiResponse
    {
        $this->load($request->param('id'));
        $this->dispatch(new $command($request->param('id')));

        return ApiResponse::data($this->detail($request->param('id')));
    }

    private function load(string $id): OfferDetail
    {
        return $this->ask(new GetOffer($id));
    }

    /** @return array<string, mixed> */
    private function detail(string $id): array
    {
        $detail = $this->load($id);

        return Normalizer::normalize($detail->offer) + [
            'customer' => SalesDocumentSupport::customerRef($detail->customer),
            'contact' => Normalizer::normalize($detail->contact),
            'totals' => Normalizer::normalize($detail->totals()),
        ] + DocumentPositions::forResponse($detail);
    }
}
