<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Article\Query\ListArticles;
use App\Application\Command\CommandBus;
use App\Application\Customer\Query\ListCustomers;
use App\Application\OrderConfirmation\Command\CreateOrderConfirmation;
use App\Application\OrderConfirmation\Command\CreateOrderConfirmationFromOffer;
use App\Application\OrderConfirmation\Command\DeleteOrderConfirmation;
use App\Application\OrderConfirmation\Command\MarkOrderConfirmationCancelled;
use App\Application\OrderConfirmation\Command\MarkOrderConfirmationConfirmed;
use App\Application\OrderConfirmation\Command\MarkOrderConfirmationSent;
use App\Application\OrderConfirmation\Command\UpdateOrderConfirmation;
use App\Application\OrderConfirmation\Query\GetOrderConfirmation;
use App\Application\OrderConfirmation\Query\ListOrderConfirmations;
use App\Application\Query\QueryBus;
use App\Application\Settings\Query\GetCompanySettings;
use App\Application\Settings\Query\GetPdfDesignSettings;
use App\Application\Settings\Query\GetPriceTiers;
use App\Application\Settings\Query\GetPricingSettings;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\OrderConfirmation\OrderConfirmation;
use App\Domain\OrderConfirmation\OrderConfirmationStatus;
use App\Domain\Settings\RentalPriceCalculator;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;
use App\Infrastructure\Pdf\OrderConfirmationPdfGenerator;

final class OrderConfirmationController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly VenueRepositoryInterface $venues,
        private readonly OrderConfirmationPdfGenerator $pdfGenerator,
    ) {
    }

    public function index(Request $request): void
    {
        $search = $request->query('q');
        $statusRaw = $this->nullIfEmpty($request->query('status'));
        $status = $statusRaw !== null ? OrderConfirmationStatus::tryFrom($statusRaw) : null;
        $page = Pagination::page($request);
        $pageSize = Pagination::pageSize($request, CurrentUser::get());

        $result = $this->queries->ask(new ListOrderConfirmations($search, $status, $page, $pageSize));

        $viewData = [
            'orderConfirmations' => $result->items,
            'search' => $search ?? '',
            'selectedStatus' => $status,
            'statuses' => OrderConfirmationStatus::cases(),
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'totalCount' => $result->totalCount,
            'totalPages' => $result->totalPages(),
            'paginationBaseUrl' => '/auftragsbestaetigungen',
            'paginationQuery' => array_filter([
                'q' => $search ?? '',
                'status' => $statusRaw ?? '',
            ], static fn ($value) => $value !== ''),
        ];

        if (Pagination::isAjax($request)) {
            echo View::render('order-confirmations/_table', $viewData);

            return;
        }

        echo View::page('order-confirmations/index', $viewData, activeNav: 'order_confirmations', title: t('order_confirmations.title'));
    }

    public function create(Request $request): void
    {
        $this->renderForm(null, [], []);
    }

    public function store(Request $request): void
    {
        $command = new CreateOrderConfirmation(
            customerId: $request->input('customer_id', '') ?? '',
            startDate: $request->input('start_date', '') ?? '',
            endDate: $request->input('end_date', '') ?? '',
            eventLocation: $request->input('event_location', '') ?? '',
            note: $request->input('note', '') ?? '',
            items: $this->itemsFromRequest($request),
            accessoryFlatRateEnabled: $request->input('accessory_flat_rate_enabled') === '1',
            discountType: $this->nullIfEmpty($request->input('discount_type')),
            discountValue: $request->input('discount_value'),
            freeTextItems: $this->freeTextItemsFromRequest($request),
            sectionTexts: $this->sectionTextsFromRequest($request),
        );

        try {
            $id = $this->commands->dispatch($command);
            Flash::success(t('order_confirmations.flash.created'));
            Response::redirect('/auftragsbestaetigungen/' . $id);
        } catch (ValidationException $e) {
            $this->renderForm(null, $e->errors(), $this->oldFromCommand($command));
        }
    }

    /** Erzeugt eine neue Auftragsbestätigung aus einem bestehenden Angebot (siehe CreateOrderConfirmationFromOfferHandler) und leitet auf deren Bearbeitungsseite weiter. */
    public function createFromOffer(Request $request): void
    {
        $offerId = $request->param('id');

        try {
            $id = $this->commands->dispatch(new CreateOrderConfirmationFromOffer($offerId));
            Flash::success(t('order_confirmations.flash.created_from_offer'));
            Response::redirect('/auftragsbestaetigungen/' . $id . '/bearbeiten');
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function show(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetOrderConfirmation($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $venue = $detail->orderConfirmation->venueId !== null ? $this->venues->findById($detail->orderConfirmation->venueId) : null;

        echo View::page('order-confirmations/show', [
            'detail' => $detail,
            'venue' => $venue,
        ], activeNav: 'order_confirmations', title: t('order_confirmations.page_title.detail'));
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetOrderConfirmation($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $this->renderForm($detail->orderConfirmation, [], [], $detail->orderConfirmation->id);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        $command = new UpdateOrderConfirmation(
            id: $id,
            customerId: $request->input('customer_id', '') ?? '',
            startDate: $request->input('start_date', '') ?? '',
            endDate: $request->input('end_date', '') ?? '',
            eventLocation: $request->input('event_location', '') ?? '',
            note: $request->input('note', '') ?? '',
            items: $this->itemsFromRequest($request),
            accessoryFlatRateEnabled: $request->input('accessory_flat_rate_enabled') === '1',
            discountType: $this->nullIfEmpty($request->input('discount_type')),
            discountValue: $request->input('discount_value'),
            freeTextItems: $this->freeTextItemsFromRequest($request),
            sectionTexts: $this->sectionTextsFromRequest($request),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('order_confirmations.flash.updated'));
            Response::redirect('/auftragsbestaetigungen/' . $id);
        } catch (ValidationException $e) {
            $detail = $this->queries->ask(new GetOrderConfirmation($id));
            $this->renderForm($detail->orderConfirmation, $e->errors(), $this->oldFromCommand($command), $id);
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/auftragsbestaetigungen/' . $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteOrderConfirmation($id));
            Flash::success(t('order_confirmations.flash.deleted'));
            Response::redirect('/auftragsbestaetigungen');
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/auftragsbestaetigungen/' . $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function markSent(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new MarkOrderConfirmationSent($id));
            Flash::success(t('order_confirmations.flash.marked_sent'));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/auftragsbestaetigungen/' . $id);
    }

    public function markConfirmed(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new MarkOrderConfirmationConfirmed($id));
            Flash::success(t('order_confirmations.flash.marked_confirmed'));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/auftragsbestaetigungen/' . $id);
    }

    public function markCancelled(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new MarkOrderConfirmationCancelled($id));
            Flash::success(t('order_confirmations.flash.marked_cancelled'));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/auftragsbestaetigungen/' . $id);
    }

    public function exportPdf(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetOrderConfirmation($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $companySettings = $this->queries->ask(new GetCompanySettings());
        $pdfDesignSettings = $this->queries->ask(new GetPdfDesignSettings());
        $pdf = $this->pdfGenerator->generate($detail, $companySettings, $pdfDesignSettings);

        $filename = 'Auftragsbestaetigung-' . $detail->orderConfirmation->orderConfirmationNumber . '.pdf';

        Response::download($pdf, $filename, 'application/pdf');
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderForm(?OrderConfirmation $orderConfirmation, array $errors, array $old, ?string $editingId = null): void
    {
        $customers = $this->queries->ask(new ListCustomers())->items;
        $venues = $this->venues->findAll();
        $articles = $this->queries->ask(new ListArticles(onlyActive: true))->items;
        $priceTiers = $this->queries->ask(new GetPriceTiers());
        $pricingSettings = $this->queries->ask(new GetPricingSettings());

        $priceSuggestions = RentalPriceCalculator::calculate('1', $priceTiers);

        $existingItems = [];
        $existingFreeTextItems = [];
        $existingSectionTexts = [];
        if ($orderConfirmation !== null) {
            $detail = $this->queries->ask(new GetOrderConfirmation($orderConfirmation->id));
            foreach ($detail->items as $entry) {
                $existingItems[] = [
                    'article_id' => $entry->item->articleId,
                    'quantity' => $entry->item->quantity,
                    'unit_price' => $entry->item->unitPricePerDay,
                    'line_note' => $entry->item->lineNote ?? '',
                    'sort_order' => $entry->item->sortOrder,
                ];
            }
            foreach ($detail->freeTextItems as $item) {
                $existingFreeTextItems[] = [
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unitPrice,
                    'sort_order' => $item->sortOrder,
                ];
            }
            foreach ($detail->sectionTexts as $sectionText) {
                $existingSectionTexts[] = [
                    'heading' => $sectionText->heading,
                    'sort_order' => $sectionText->sortOrder,
                ];
            }
        }

        echo View::page('order-confirmations/form', [
            'orderConfirmation' => $orderConfirmation,
            'customers' => $customers,
            'venues' => $venues,
            'articles' => $articles,
            'tierMultipliers' => $priceSuggestions,
            'errors' => $errors,
            'old' => $old,
            'editingId' => $editingId,
            'existingItems' => $existingItems,
            'existingFreeTextItems' => $existingFreeTextItems,
            'existingSectionTexts' => $existingSectionTexts,
            'pricingSettings' => $pricingSettings,
        ], activeNav: 'order_confirmations', title: $orderConfirmation !== null ? t('order_confirmations.edit') : t('order_confirmations.new'));
    }

    /** @return array<int, array{article_id: string, quantity: string, unit_price: string, line_note: string, sort_order: string}> */
    private function itemsFromRequest(Request $request): array
    {
        $articleIds = $request->post['article_id'] ?? [];
        $quantities = $request->post['quantity'] ?? [];
        $unitPrices = $request->post['unit_price'] ?? [];
        $lineNotes = $request->post['line_note'] ?? [];
        $sortOrders = $request->post['sort_order'] ?? [];

        if (!is_array($articleIds)) {
            return [];
        }

        $items = [];
        foreach ($articleIds as $index => $articleId) {
            $items[] = [
                'article_id' => (string) $articleId,
                'quantity' => (string) ($quantities[$index] ?? ''),
                'unit_price' => (string) ($unitPrices[$index] ?? ''),
                'line_note' => (string) ($lineNotes[$index] ?? ''),
                'sort_order' => (string) ($sortOrders[$index] ?? $index),
            ];
        }

        return $items;
    }

    /** @return array<int, array{description: string, quantity: string, unit_price: string, sort_order: string}> */
    private function freeTextItemsFromRequest(Request $request): array
    {
        $descriptions = $request->post['free_text_description'] ?? [];
        $quantities = $request->post['free_text_quantity'] ?? [];
        $unitPrices = $request->post['free_text_unit_price'] ?? [];
        $sortOrders = $request->post['free_text_sort_order'] ?? [];

        if (!is_array($descriptions)) {
            return [];
        }

        $items = [];
        foreach ($descriptions as $index => $description) {
            $items[] = [
                'description' => (string) $description,
                'quantity' => (string) ($quantities[$index] ?? '1'),
                'unit_price' => (string) ($unitPrices[$index] ?? '0'),
                'sort_order' => (string) ($sortOrders[$index] ?? (1000 + $index)),
            ];
        }

        return $items;
    }

    /** @return array<int, array{heading: string, sort_order: string}> */
    private function sectionTextsFromRequest(Request $request): array
    {
        $headings = $request->post['section_heading'] ?? [];
        $sortOrders = $request->post['section_sort_order'] ?? [];

        if (!is_array($headings)) {
            return [];
        }

        $items = [];
        foreach ($headings as $index => $heading) {
            $items[] = [
                'heading' => (string) $heading,
                'sort_order' => (string) ($sortOrders[$index] ?? (2000 + $index)),
            ];
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function oldFromCommand(CreateOrderConfirmation|UpdateOrderConfirmation $command): array
    {
        return [
            'customer_id' => $command->customerId,
            'start_date' => $command->startDate,
            'end_date' => $command->endDate,
            'event_location' => $command->eventLocation,
            'note' => $command->note,
            'items' => $command->items,
            'accessory_flat_rate_enabled' => $command->accessoryFlatRateEnabled ? '1' : '',
            'discount_type' => $command->discountType ?? '',
            'discount_value' => $command->discountValue ?? '',
            'free_text_items' => $command->freeTextItems,
            'section_texts' => $command->sectionTexts,
        ];
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
