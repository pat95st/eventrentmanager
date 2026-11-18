<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\Command\CommandBus;
use App\Application\OrderConfirmation\Command\CreateOrderConfirmation;
use App\Application\OrderConfirmation\Command\DeleteOrderConfirmation;
use App\Application\OrderConfirmation\Command\MarkOrderConfirmationCancelled;
use App\Application\OrderConfirmation\Command\MarkOrderConfirmationConfirmed;
use App\Application\OrderConfirmation\Command\MarkOrderConfirmationSent;
use App\Application\OrderConfirmation\Command\UpdateOrderConfirmation;
use App\Application\OrderConfirmation\Query\GetOrderConfirmation;
use App\Application\OrderConfirmation\Query\ListOrderConfirmations;
use App\Application\OrderConfirmation\Query\OrderConfirmationDetail;
use App\Application\Query\QueryBus;
use App\Domain\OrderConfirmation\OrderConfirmationStatus;
use App\Infrastructure\Http\Api\ApiController;
use App\Infrastructure\Http\Api\ApiException;
use App\Infrastructure\Http\Api\ApiRequest;
use App\Infrastructure\Http\Api\ApiResponse;
use App\Infrastructure\Http\Api\CommandFactory;
use App\Infrastructure\Http\Api\DocumentPositions;
use App\Infrastructure\Http\Api\DocumentRenderer;
use App\Infrastructure\Http\Api\Normalizer;

/** /api/v1/order-confirmations — scopes sales:read / sales:write. */
final class OrderConfirmationApiController extends ApiController
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
        $statusEnum = $status !== null && $status !== '' ? (OrderConfirmationStatus::tryFrom($status) ?? throw ApiException::badRequest('Unbekannter Status "' . $status . '".')) : null;
        $result = $this->ask(new ListOrderConfirmations(status: $statusEnum, page: 1, pageSize: 1_000_000));
        $items = [];
        foreach ($result->items as $entry) {
            $items[] = Normalizer::normalize($entry->orderConfirmation) + ['customer' => SalesDocumentSupport::customerRef($entry->customer)];
        }

        return $this->listResponse($request, $items);
    }

    public function show(ApiRequest $request): ApiResponse
    {
        return ApiResponse::data($this->detail($request->param('id')));
    }

    public function store(ApiRequest $request): ApiResponse
    {
        $id = $this->run(CreateOrderConfirmation::class, $request);

        return $this->created($this->detail((string) $id));
    }

    public function update(ApiRequest $request): ApiResponse
    {
        $id = $request->param('id');
        $detail = $this->load($id);
        $current = CommandFactory::props($detail->orderConfirmation) + DocumentPositions::rows($detail) + [
            'rentalId' => $detail->orderConfirmation->sourceRentalId,
            'note' => $detail->orderConfirmation->note ?? '',
            'eventLocation' => $detail->orderConfirmation->eventLocation ?? '',
        ];
        $this->run(UpdateOrderConfirmation::class, $request, ['id' => $id], $current);

        return ApiResponse::data($this->detail($id));
    }

    public function destroy(ApiRequest $request): ApiResponse
    {
        $this->load($request->param('id'));
        $this->dispatch(new DeleteOrderConfirmation($request->param('id')));

        return ApiResponse::noContent();
    }

    public function markSent(ApiRequest $request): ApiResponse
    {
        return $this->transition($request, MarkOrderConfirmationSent::class);
    }

    public function confirm(ApiRequest $request): ApiResponse
    {
        return $this->transition($request, MarkOrderConfirmationConfirmed::class);
    }

    public function cancel(ApiRequest $request): ApiResponse
    {
        return $this->transition($request, MarkOrderConfirmationCancelled::class);
    }

    public function pdf(ApiRequest $request): ApiResponse
    {
        [$name, $pdf] = $this->documents->orderConfirmationPdf($this->load($request->param('id')));

        return $this->pdfResponse($pdf, $name);
    }

    /** @param class-string $command */
    private function transition(ApiRequest $request, string $command): ApiResponse
    {
        $this->load($request->param('id'));
        $this->dispatch(new $command($request->param('id')));

        return ApiResponse::data($this->detail($request->param('id')));
    }

    private function load(string $id): OrderConfirmationDetail
    {
        return $this->ask(new GetOrderConfirmation($id));
    }

    /** @return array<string, mixed> */
    private function detail(string $id): array
    {
        $detail = $this->load($id);

        return Normalizer::normalize($detail->orderConfirmation) + [
            'customer' => SalesDocumentSupport::customerRef($detail->customer),
            'contact' => Normalizer::normalize($detail->contact),
            'totals' => Normalizer::normalize($detail->totals()),
        ] + DocumentPositions::forResponse($detail);
    }
}
