<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Article\Query\ListArticles;
use App\Application\Command\CommandBus;
use App\Application\Customer\Query\ListCustomers;
use App\Application\Invoice\Command\CreateInvoiceFromOffer;
use App\Application\LaborTime\Query\ListLaborTimeArticles;
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
use App\Application\Query\QueryBus;
use App\Application\Settings\Query\GetCompanySettings;
use App\Application\Settings\Query\GetLegalSettings;
use App\Application\Settings\Query\GetOfferPdfSettings;
use App\Application\Settings\Query\GetPdfDesignSettings;
use App\Application\Settings\Query\GetPriceTiers;
use App\Application\Settings\Query\GetPricingSettings;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Offer\Offer;
use App\Domain\Offer\OfferStatus;
use App\Domain\Settings\RentalPriceCalculator;
use App\Domain\Stock\SaleStockLotRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;
use App\Infrastructure\Mail\MailerException;
use App\Infrastructure\Pdf\OfferPdfGenerator;

final class OfferController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly VenueRepositoryInterface $venues,
        private readonly OfferPdfGenerator $pdfGenerator,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly SaleStockLotRepositoryInterface $stockLots,
    ) {
    }

    public function index(Request $request): void
    {
        $search = $request->query('q');
        $statusRaw = $this->nullIfEmpty($request->query('status'));
        $status = $statusRaw !== null ? OfferStatus::tryFrom($statusRaw) : null;
        $page = Pagination::page($request);
        $pageSize = Pagination::pageSize($request, CurrentUser::get());

        $result = $this->queries->ask(new ListOffers($search, $status, $page, $pageSize));

        $viewData = [
            'offers' => $result->items,
            'search' => $search ?? '',
            'selectedStatus' => $status,
            'statuses' => OfferStatus::cases(),
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'totalCount' => $result->totalCount,
            'totalPages' => $result->totalPages(),
            'paginationBaseUrl' => '/angebote',
            'paginationQuery' => array_filter([
                'q' => $search ?? '',
                'status' => $statusRaw ?? '',
            ], static fn ($value) => $value !== ''),
        ];

        if (Pagination::isAjax($request)) {
            echo View::render('offers/_table', $viewData);

            return;
        }

        echo View::page('offers/index', $viewData, activeNav: 'offers', title: t('offers.title'));
    }

    public function create(Request $request): void
    {
        $this->renderForm(null, [], []);
    }

    public function store(Request $request): void
    {
        $command = new CreateOffer(
            customerId: $request->input('customer_id', '') ?? '',
            startDate: $request->input('start_date', '') ?? '',
            endDate: $request->input('end_date', '') ?? '',
            eventLocation: $request->input('event_location', '') ?? '',
            validUntil: $request->input('valid_until', '') ?? '',
            note: $request->input('note', '') ?? '',
            items: $this->itemsFromRequest($request),
            accessoryFlatRateEnabled: $request->input('accessory_flat_rate_enabled') === '1',
            discountType: $this->nullIfEmpty($request->input('discount_type')),
            discountValue: $request->input('discount_value'),
            laborItems: $this->laborItemsFromRequest($request),
            saleItems: $this->saleItemsFromRequest($request),
            freeTextItems: $this->freeTextItemsFromRequest($request),
            sectionTexts: $this->sectionTextsFromRequest($request),
        );

        try {
            $offerId = $this->commands->dispatch($command);
            Flash::success(t('offers.flash.created'));
            Response::redirect('/angebote/' . $offerId);
        } catch (ValidationException $e) {
            $this->renderForm(null, $e->errors(), $this->oldFromCommand($command));
        }
    }

    public function show(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetOffer($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $venue = $detail->offer->venueId !== null ? $this->venues->findById($detail->offer->venueId) : null;

        echo View::page('offers/show', [
            'detail' => $detail,
            'venue' => $venue,
        ], activeNav: 'offers', title: t('offers.page_title.detail'));
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetOffer($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        // Enthält dieses (noch im Entwurf befindliche) Angebot bereits eine
        // abgerechnete Verkaufsposition, verweigert UpdateOfferHandler JEDE
        // weitere Bearbeitung (siehe dortige Begründung) — anders als beim
        // Status-Lock (isEditable()) gibt es hierfür keinen funktionierenden
        // "nur gültig-bis"-Teilmodus, da der Handler diesen Kurzschluss nur
        // für Angebote außerhalb des Entwurfs-Status nimmt. Das Formular wird
        // daher für diesen Fall gar nicht erst angezeigt.
        foreach ($detail->saleItems as $entry) {
            if ($entry->item->isFulfilled()) {
                Flash::error(t('offers.form.sale_items_locked_notice'));
                Response::redirect('/angebote/' . $id);

                return;
            }
        }

        $this->renderForm($detail->offer, [], [], $detail->offer->id);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        $command = new UpdateOffer(
            id: $id,
            customerId: $request->input('customer_id', '') ?? '',
            startDate: $request->input('start_date', '') ?? '',
            endDate: $request->input('end_date', '') ?? '',
            eventLocation: $request->input('event_location', '') ?? '',
            validUntil: $request->input('valid_until', '') ?? '',
            note: $request->input('note', '') ?? '',
            items: $this->itemsFromRequest($request),
            accessoryFlatRateEnabled: $request->input('accessory_flat_rate_enabled') === '1',
            discountType: $this->nullIfEmpty($request->input('discount_type')),
            discountValue: $request->input('discount_value'),
            laborItems: $this->laborItemsFromRequest($request),
            saleItems: $this->saleItemsFromRequest($request),
            freeTextItems: $this->freeTextItemsFromRequest($request),
            sectionTexts: $this->sectionTextsFromRequest($request),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('offers.flash.updated'));
            Response::redirect('/angebote/' . $id);
        } catch (ValidationException $e) {
            $detail = $this->queries->ask(new GetOffer($id));
            $this->renderForm($detail->offer, $e->errors(), $this->oldFromCommand($command), $id);
        } catch (ConflictException $e) {
            // Mindestens eine Verkaufsposition dieses Angebots wurde zwischen
            // dem Öffnen des Formulars und dem Absenden bereits abgerechnet
            // (siehe UpdateOfferHandler::hasFulfilledSaleItems()) — die
            // Bearbeitungsseite würde dafür ohnehin sofort wieder zur
            // Detailseite umleiten (siehe edit()), daher direkt dorthin.
            Flash::error($e->getMessage());
            Response::redirect('/angebote/' . $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteOffer($id));
            Flash::success(t('offers.flash.deleted'));
            Response::redirect('/angebote');
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/angebote/' . $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function markSent(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new MarkOfferSent($id));
            Flash::success(t('offers.flash.marked_sent'));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/angebote/' . $id);
    }

    public function markAccepted(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new MarkOfferAccepted($id));
            Flash::success(t('offers.flash.marked_accepted'));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/angebote/' . $id);
    }

    public function markDeclined(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new MarkOfferDeclined($id));
            Flash::success(t('offers.flash.marked_declined'));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/angebote/' . $id);
    }

    public function convertToRental(Request $request): void
    {
        $id = $request->param('id');

        try {
            $rentalId = $this->commands->dispatch(new ConvertOfferToRental($id));
            Flash::success(t('offers.flash.converted'));
            Response::redirect('/veranstaltungen/' . $rentalId);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/angebote/' . $id);
    }

    /** Erstellt aus diesem Angebot eine neue Entwurfs-Rechnung (siehe CreateInvoiceFromOfferHandler) und leitet auf deren Bearbeitungsseite weiter, damit der Benutzer sie vor dem Versenden prüfen/anpassen kann. */
    public function createInvoice(Request $request): void
    {
        $id = $request->param('id');

        try {
            $invoiceId = $this->commands->dispatch(new CreateInvoiceFromOffer($id));
            Flash::success(t('offers.flash.invoice_created'));
            Response::redirect('/rechnungen/' . $invoiceId . '/bearbeiten');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/angebote/' . $id);
    }

    public function exportPdf(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetOffer($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $companySettings = $this->queries->ask(new GetCompanySettings());
        $legalSettings = $this->queries->ask(new GetLegalSettings());
        $offerPdfSettings = $this->queries->ask(new GetOfferPdfSettings());
        $pdfDesignSettings = $this->queries->ask(new GetPdfDesignSettings());
        $layout = $this->queries->ask(new \App\Application\Settings\Query\GetPdfLayoutSettings(\App\Domain\Settings\PdfLayout\PdfDocumentType::Offer));
        $pdf = $this->pdfGenerator->generate($detail, $companySettings, $legalSettings, $offerPdfSettings, $pdfDesignSettings, $layout);

        $filename = 'Angebot-' . strtoupper(substr($detail->offer->id, 0, 8)) . '.pdf';

        Response::download($pdf, $filename, 'application/pdf');
    }

    public function emailPdf(Request $request): void
    {
        $id = $request->param('id');
        $recipient = trim($request->input('recipient_email', '') ?? '');
        $note = trim($request->input('note', '') ?? '');

        try {
            $this->commands->dispatch(new EmailOfferPdf($id, $recipient, $note !== '' ? $note : null));
            Flash::success(t('offers.flash.pdf_emailed', ['recipient' => $recipient]));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (MailerException $e) {
            Flash::error(t('offers.flash.email_failed', ['error' => $e->getMessage()]));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/angebote/' . $id);
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderForm(?Offer $offer, array $errors, array $old, ?string $editingId = null): void
    {
        $customers = $this->queries->ask(new ListCustomers())->items;
        $venues = $this->venues->findAll();
        $articles = $this->queries->ask(new ListArticles(onlyActive: true))->items;
        $priceTiers = $this->queries->ask(new GetPriceTiers());
        $pricingSettings = $this->queries->ask(new GetPricingSettings());

        // Preisstaffelung + Tagesmietsatz je Artikel werden dem Formular als
        // JSON mitgegeben, damit der Preisvorschlag pro Position rein
        // clientseitig berechnet werden kann (siehe offers/form.php) — ohne
        // zusätzlichen AJAX-Roundtrip, aber weiterhin frei überschreibbar.
        $priceSuggestions = RentalPriceCalculator::calculate('1', $priceTiers);

        $laborArticles = $this->queries->ask(new ListLaborTimeArticles());

        $existingItems = [];
        $existingLaborItems = [];
        $existingSaleItems = [];
        $existingFreeTextItems = [];
        $existingSectionTexts = [];
        $saleItemsFulfilled = false;
        if ($offer !== null) {
            $detail = $this->queries->ask(new GetOffer($offer->id));
            foreach ($detail->items as $entry) {
                $existingItems[] = [
                    'article_id' => $entry->item->articleId,
                    'quantity' => $entry->item->quantity,
                    'unit_price' => $entry->item->unitPricePerDay,
                    'line_note' => $entry->item->lineNote ?? '',
                    'sort_order' => $entry->item->sortOrder,
                ];
            }
            foreach ($detail->laborItems as $entry) {
                $existingLaborItems[] = [
                    'labor_article_id' => $entry->item->laborTimeArticleId,
                    'hours' => $entry->item->hours,
                ];
            }
            foreach ($detail->saleItems as $entry) {
                $existingSaleItems[] = [
                    'article_id' => $entry->item->articleId,
                    'source' => $entry->item->inventoryNumberId !== null ? 'inventory_number' : 'stock',
                    'inventory_number_id' => $entry->item->inventoryNumberId ?? '',
                    'quantity' => $entry->item->quantity,
                    'unit_price' => $entry->item->unitPrice,
                    'line_note' => $entry->item->lineNote ?? '',
                ];
                if ($entry->item->isFulfilled()) {
                    $saleItemsFulfilled = true;
                }
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

        // Je Artikel die noch verkaufbaren Exemplare aus dem Vermietpool
        // (findActiveByArticle — OHNE bereits verkaufte) sowie der aktuelle
        // Lagerbestand des reinen Verkaufsbestands (sumRemainingByArticle):
        // Grundlage für die Quellen-Auswahl je Verkaufsposition im Formular
        // (siehe offers/form.php).
        $saleInventoryNumbersJson = [];
        $saleStockJson = [];
        foreach ($articles as $article) {
            $saleInventoryNumbersJson[$article->id] = array_map(
                static fn ($inventoryNumber) => ['id' => $inventoryNumber->id, 'number' => $inventoryNumber->number],
                $this->inventoryNumbers->findActiveByArticle($article->id)
            );
            $saleStockJson[$article->id] = $this->stockLots->sumRemainingByArticle($article->id);
        }

        echo View::page('offers/form', [
            'offer' => $offer,
            'customers' => $customers,
            'venues' => $venues,
            'articles' => $articles,
            'tierMultipliers' => $priceSuggestions, // Tag => Faktor (auf Basis von Tagesmietsatz "1")
            'errors' => $errors,
            'old' => $old,
            'editingId' => $editingId,
            'existingItems' => $existingItems,
            'pricingSettings' => $pricingSettings,
            'laborArticles' => $laborArticles,
            'existingLaborItems' => $existingLaborItems,
            'existingSaleItems' => $existingSaleItems,
            'existingFreeTextItems' => $existingFreeTextItems,
            'existingSectionTexts' => $existingSectionTexts,
            'saleItemsFulfilled' => $saleItemsFulfilled,
            'saleInventoryNumbers' => $saleInventoryNumbersJson,
            'saleStock' => $saleStockJson,
        ], activeNav: 'offers', title: $offer !== null ? t('offers.edit') : t('offers.new'));
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

    /** @return array<int, array{article_id: string, source: string, inventory_number_id: string, quantity: string, unit_price: string, line_note: string}> */
    private function saleItemsFromRequest(Request $request): array
    {
        $articleIds = $request->post['sale_article_id'] ?? [];
        $sources = $request->post['sale_source'] ?? [];
        $inventoryNumberIds = $request->post['sale_inventory_number_id'] ?? [];
        $quantities = $request->post['sale_quantity'] ?? [];
        $unitPrices = $request->post['sale_unit_price'] ?? [];
        $lineNotes = $request->post['sale_line_note'] ?? [];

        if (!is_array($articleIds)) {
            return [];
        }

        $items = [];
        foreach ($articleIds as $index => $articleId) {
            $items[] = [
                'article_id' => (string) $articleId,
                'source' => (string) ($sources[$index] ?? ''),
                'inventory_number_id' => (string) ($inventoryNumberIds[$index] ?? ''),
                'quantity' => (string) ($quantities[$index] ?? ''),
                'unit_price' => (string) ($unitPrices[$index] ?? ''),
                'line_note' => (string) ($lineNotes[$index] ?? ''),
            ];
        }

        return $items;
    }

    /** @return array<int, array{labor_article_id: string, hours: string}> */
    private function laborItemsFromRequest(Request $request): array
    {
        $laborArticleIds = $request->post['labor_article_id'] ?? [];
        $hoursValues = $request->post['hours'] ?? [];

        if (!is_array($laborArticleIds)) {
            return [];
        }

        $items = [];
        foreach ($laborArticleIds as $index => $laborArticleId) {
            $items[] = [
                'labor_article_id' => (string) $laborArticleId,
                'hours' => (string) ($hoursValues[$index] ?? ''),
            ];
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function oldFromCommand(CreateOffer|UpdateOffer $command): array
    {
        return [
            'customer_id' => $command->customerId,
            'start_date' => $command->startDate,
            'end_date' => $command->endDate,
            'event_location' => $command->eventLocation,
            'valid_until' => $command->validUntil,
            'note' => $command->note,
            'items' => $command->items,
            'accessory_flat_rate_enabled' => $command->accessoryFlatRateEnabled ? '1' : '',
            'discount_type' => $command->discountType ?? '',
            'discount_value' => $command->discountValue ?? '',
            'labor_items' => $command->laborItems,
            'sale_items' => $command->saleItems,
            'free_text_items' => $command->freeTextItems,
            'section_texts' => $command->sectionTexts,
        ];
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
