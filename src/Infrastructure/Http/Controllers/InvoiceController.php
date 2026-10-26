<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Article\Query\ListArticles;
use App\Application\Manufacturer\Query\ListManufacturers;
use App\Application\Command\CommandBus;
use App\Application\Customer\Query\ListCustomers;
use App\Application\Dunning\Query\ListDunningLevels;
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
use App\Application\Invoice\Query\ListInvoices;
use App\Application\LaborTime\Query\ListLaborTimeArticles;
use App\Application\Query\QueryBus;
use App\Application\Settings\Query\GetCompanySettings;
use App\Application\Settings\Query\GetInvoicePdfSettings;
use App\Application\Settings\Query\GetInvoiceSettings;
use App\Application\Settings\Query\GetLegalSettings;
use App\Application\Settings\Query\GetPdfDesignSettings;
use App\Application\Settings\Query\GetPriceTiers;
use App\Application\Settings\Query\GetPricingSettings;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Settings\RentalPriceCalculator;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Infrastructure\EInvoice\XRechnungGenerator;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;
use App\Infrastructure\Mail\MailerException;
use App\Infrastructure\Pdf\InvoicePdfGenerator;

final class InvoiceController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly VenueRepositoryInterface $venues,
        private readonly InvoicePdfGenerator $pdfGenerator,
        private readonly XRechnungGenerator $xRechnungGenerator,
        private readonly RentalRepositoryInterface $rentals,
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    public function index(Request $request): void
    {
        $search = $request->query('q');
        $statusRaw = $this->nullIfEmpty($request->query('status'));
        $status = $statusRaw !== null ? InvoiceStatus::tryFrom($statusRaw) : null;
        $page = Pagination::page($request);
        $pageSize = Pagination::pageSize($request, CurrentUser::get());

        $result = $this->queries->ask(new ListInvoices($search, $status, $page, $pageSize));

        $viewData = [
            'invoices' => $result->items,
            'search' => $search ?? '',
            'selectedStatus' => $status,
            'statuses' => InvoiceStatus::cases(),
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'totalCount' => $result->totalCount,
            'totalPages' => $result->totalPages(),
            'paginationBaseUrl' => '/rechnungen',
            'paginationQuery' => array_filter([
                'q' => $search ?? '',
                'status' => $statusRaw ?? '',
            ], static fn ($value) => $value !== ''),
        ];

        if (Pagination::isAjax($request)) {
            echo View::render('invoices/_table', $viewData);

            return;
        }

        echo View::page('invoices/index', $viewData, activeNav: 'invoices', title: t('invoices.title'));
    }

    public function create(Request $request): void
    {
        $this->renderForm(null, [], []);
    }

    public function store(Request $request): void
    {
        $command = new CreateInvoice(
            customerId: $request->input('customer_id', '') ?? '',
            startDate: $request->input('start_date', '') ?? '',
            endDate: $request->input('end_date', '') ?? '',
            eventLocation: $request->input('event_location', '') ?? '',
            buyerReference: $request->input('buyer_reference', '') ?? '',
            note: $request->input('note', '') ?? '',
            items: $this->itemsFromRequest($request),
            accessoryFlatRateEnabled: $request->input('accessory_flat_rate_enabled') === '1',
            discountType: $this->nullIfEmpty($request->input('discount_type')),
            discountValue: $request->input('discount_value'),
            laborItems: $this->laborItemsFromRequest($request),
            freeTextItems: $this->freeTextItemsFromRequest($request),
            sectionTexts: $this->sectionTextsFromRequest($request),
            closingText: $request->input('closing_text'),
            rentalId: $this->nullIfEmpty($request->input('rental_id')),
            flatRateEnabled: $request->input('flat_rate_enabled') === '1',
            flatRateAmount: $request->input('flat_rate_amount'),
            logisticsMethod: $this->nullIfEmpty($request->input('logistics_method')),
            logisticsDate1: $this->nullIfEmpty($request->input('logistics_date1')),
            logisticsDate2: $this->nullIfEmpty($request->input('logistics_date2')),
        );

        try {
            $invoiceId = $this->commands->dispatch($command);
            Flash::success(t('invoices.flash.created'));
            Response::redirect('/rechnungen/' . $invoiceId);
        } catch (ValidationException $e) {
            $this->renderForm(null, $e->errors(), $this->oldFromCommand($command));
        }
    }

    public function show(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetInvoice($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $venue = $detail->invoice->venueId !== null ? $this->venues->findById($detail->invoice->venueId) : null;
        $invoiceSettings = $this->queries->ask(new GetInvoiceSettings());
        $dunningNotices = $this->queries->ask(new ListDunningNoticesForInvoice($id));
        $dunningLevels = $this->queries->ask(new ListDunningLevels());
        $linkedRental = $detail->invoice->sourceRentalId !== null ? $this->rentals->findById($detail->invoice->sourceRentalId) : null;

        echo View::page('invoices/show', [
            'detail' => $detail,
            'venue' => $venue,
            'invoiceSettings' => $invoiceSettings,
            'dunningNotices' => $dunningNotices,
            'dunningLevels' => $dunningLevels,
            'linkedRental' => $linkedRental,
        ], activeNav: 'invoices', title: t('invoices.page_title.detail'));
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetInvoice($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $this->renderForm($detail->invoice, [], [], $detail->invoice->id);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        $command = new UpdateInvoice(
            id: $id,
            customerId: $request->input('customer_id', '') ?? '',
            startDate: $request->input('start_date', '') ?? '',
            endDate: $request->input('end_date', '') ?? '',
            eventLocation: $request->input('event_location', '') ?? '',
            buyerReference: $request->input('buyer_reference', '') ?? '',
            note: $request->input('note', '') ?? '',
            items: $this->itemsFromRequest($request),
            accessoryFlatRateEnabled: $request->input('accessory_flat_rate_enabled') === '1',
            discountType: $this->nullIfEmpty($request->input('discount_type')),
            discountValue: $request->input('discount_value'),
            laborItems: $this->laborItemsFromRequest($request),
            freeTextItems: $this->freeTextItemsFromRequest($request),
            sectionTexts: $this->sectionTextsFromRequest($request),
            closingText: $request->input('closing_text'),
            rentalId: $this->nullIfEmpty($request->input('rental_id')),
            flatRateEnabled: $request->input('flat_rate_enabled') === '1',
            flatRateAmount: $request->input('flat_rate_amount'),
            logisticsMethod: $this->nullIfEmpty($request->input('logistics_method')),
            logisticsDate1: $this->nullIfEmpty($request->input('logistics_date1')),
            logisticsDate2: $this->nullIfEmpty($request->input('logistics_date2')),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('invoices.flash.updated'));
            Response::redirect('/rechnungen/' . $id);
        } catch (ValidationException $e) {
            $detail = $this->queries->ask(new GetInvoice($id));
            $this->renderForm($detail->invoice, $e->errors(), $this->oldFromCommand($command), $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/rechnungen/' . $id);
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteInvoice($id));
            Flash::success(t('invoices.flash.deleted'));
            Response::redirect('/rechnungen');
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/rechnungen/' . $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function markSent(Request $request): void
    {
        $id = $request->param('id');

        try {
            $invoiceNumber = $this->commands->dispatch(new MarkInvoiceSent($id));
            Flash::success(t('invoices.flash.marked_sent', ['number' => (string) $invoiceNumber]));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/rechnungen/' . $id);
    }

    public function markPaid(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new MarkInvoicePaid($id));
            Flash::success(t('invoices.flash.marked_paid'));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/rechnungen/' . $id);
    }

    public function markCancelled(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new MarkInvoiceCancelled($id));
            Flash::success(t('invoices.flash.marked_cancelled'));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/rechnungen/' . $id);
    }

    public function exportPdf(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetInvoice($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $companySettings = $this->queries->ask(new GetCompanySettings());
        $invoiceSettings = $this->queries->ask(new GetInvoiceSettings());
        $legalSettings = $this->queries->ask(new GetLegalSettings());
        $invoicePdfSettings = $this->queries->ask(new GetInvoicePdfSettings());
        $pdfDesignSettings = $this->queries->ask(new GetPdfDesignSettings());
        $layout = $this->queries->ask(new \App\Application\Settings\Query\GetPdfLayoutSettings(\App\Domain\Settings\PdfLayout\PdfDocumentType::Invoice));
        $pdf = $this->pdfGenerator->generate($detail, $companySettings, $invoiceSettings, $legalSettings, $invoicePdfSettings, $pdfDesignSettings, $layout);

        $filename = 'Rechnung-' . ($detail->invoice->invoiceNumber ?? ('Entwurf-' . strtoupper(substr($detail->invoice->id, 0, 8)))) . '.pdf';

        Response::download($pdf, $filename, 'application/pdf');
    }

    /** Lädt das XRechnung-XML herunter — nur für finalisierte (bereits versendete) Rechnungen, siehe XRechnungGenerator. */
    public function exportXRechnung(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetInvoice($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        if (!$detail->invoice->isFinalized()) {
            Flash::error(t('invoices.flash.xrechnung_not_finalized'));
            Response::redirect('/rechnungen/' . $id);

            return;
        }

        $companySettings = $this->queries->ask(new GetCompanySettings());
        $invoiceSettings = $this->queries->ask(new GetInvoiceSettings());

        try {
            $xml = $this->xRechnungGenerator->generate($detail, $companySettings, $invoiceSettings);
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/rechnungen/' . $id);

            return;
        }

        $filename = 'Rechnung-' . $detail->invoice->invoiceNumber . '.xml';

        Response::download($xml, $filename, 'application/xml');
    }

    public function emailPdf(Request $request): void
    {
        $id = $request->param('id');
        $recipient = trim($request->input('recipient_email', '') ?? '');
        $note = trim($request->input('note', '') ?? '');

        try {
            $this->commands->dispatch(new EmailInvoicePdf($id, $recipient, $note !== '' ? $note : null));

            // Zusätzlich per Messenger benachrichtigen (WhatsApp/Telegram/Signal,
            // siehe SendInvoiceSentViaMessagingHandler) — rein additiv, ein
            // Fehler dabei darf den bereits erfolgreichen E-Mail-Versand
            // nicht als Fehlschlag erscheinen lassen, daher eigener try/catch.
            try {
                $this->commands->dispatch(new SendInvoiceSentViaMessaging($id));
            } catch (\Throwable $e) {
                // Bewusst ignoriert (siehe Kommentar oben) — bei Bedarf könnte
                // hier künftig geloggt werden.
            }

            Flash::success(t('invoices.flash.pdf_emailed', ['recipient' => $recipient]));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (MailerException $e) {
            Flash::error(t('invoices.flash.email_failed', ['error' => $e->getMessage()]));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/rechnungen/' . $id);
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderForm(?Invoice $invoice, array $errors, array $old, ?string $editingId = null): void
    {
        $customers = $this->queries->ask(new ListCustomers())->items;
        $venues = $this->venues->findAll();
        $articles = $this->queries->ask(new ListArticles(onlyActive: true))->items;
        $priceTiers = $this->queries->ask(new GetPriceTiers());
        $pricingSettings = $this->queries->ask(new GetPricingSettings());

        // Siehe OfferController::renderForm() für die identische
        // Begründung dieses Musters (Preisvorschlag rein clientseitig,
        // ohne zusätzlichen AJAX-Roundtrip).
        $priceSuggestions = RentalPriceCalculator::calculate('1', $priceTiers);

        $laborArticles = $this->queries->ask(new ListLaborTimeArticles());

        // Für die clientseitige Live-Vorschau der Gesamtsumme im Formular
        // (siehe invoices/form.php): identisches Fallback-Muster wie
        // InvoiceDetail::totals() — der gesnapshotete Wert der Rechnung hat
        // Vorrang, sonst greift der aktuelle InvoiceSettings-Wert als
        // Vorschau (bei einer neuen Rechnung ist $invoice noch null, macht
        // hier also ausschließlich den Einstellungswert sichtbar).
        $invoiceSettings = $this->queries->ask(new GetInvoiceSettings());
        $isSmallBusinessPreview = $invoice !== null
            ? ($invoice->isSmallBusiness ?? $invoiceSettings->isSmallBusiness)
            : $invoiceSettings->isSmallBusiness;
        $vatRatePercentPreview = $invoice !== null
            ? ($invoice->vatRatePercent ?? $invoiceSettings->vatRatePercent)
            : $invoiceSettings->vatRatePercent;

        $existingItems = [];
        $existingLaborItems = [];
        $existingFreeTextItems = [];
        $existingSectionTexts = [];
        if ($invoice !== null) {
            $detail = $this->queries->ask(new GetInvoice($invoice->id));
            foreach ($detail->items as $entry) {
                $existingItems[] = [
                    'article_id' => $entry->item->articleId,
                    'quantity' => $entry->item->quantity,
                    'unit_price' => $entry->item->unitPricePerDay,
                    'line_note' => $entry->item->lineNote ?? '',
                    'sort_order' => $entry->item->sortOrder,
                    'is_rental_item' => $entry->item->isRentalItem ? '1' : '0',
                ];
            }
            foreach ($detail->laborItems as $entry) {
                $existingLaborItems[] = [
                    'labor_article_id' => $entry->item->laborTimeArticleId,
                    'hours' => $entry->item->hours,
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

        // Anzeige-Label "Hersteller Artikelbezeichnung" je Artikel-ID
        // (Nutzerwunsch). Siehe OfferController::renderForm() für die
        // identische Begründung, warum $articles selbst unverändert bleibt.
        $manufacturersById = [];
        foreach ($this->queries->ask(new ListManufacturers()) as $manufacturer) {
            $manufacturersById[$manufacturer->id] = $manufacturer;
        }
        $articleDisplayNames = [];
        foreach ($articles as $article) {
            $articleDisplayNames[$article->id] = ArticleDisplayName::of($article, $manufacturersById[$article->manufacturerId] ?? null);
        }

        // Anzeige-Label der verknüpften Veranstaltung (siehe
        // Invoice::$sourceRentalId/RentalController::search() für das
        // identische Label-Format) für die Vorbelegung der Live-Suche im
        // Formular (siehe invoices/form.php).
        $selectedRentalId = $invoice?->sourceRentalId;
        $selectedRentalLabel = null;
        if ($selectedRentalId !== null) {
            $rental = $this->rentals->findById($selectedRentalId);
            $rentalCustomer = $rental !== null ? $this->customers->findById($rental->customerId) : null;
            if ($rental !== null && $rentalCustomer !== null) {
                $selectedRentalLabel = sprintf(
                    '%s – %s (%s – %s)',
                    $rental->eventName,
                    $rentalCustomer->displayName(),
                    $rental->startDate->format('d.m.Y'),
                    $rental->endDate->format('d.m.Y'),
                );
            }
        }

        echo View::page('invoices/form', [
            'invoice' => $invoice,
            'customers' => $customers,
            'venues' => $venues,
            'articles' => $articles,
            'articleDisplayNames' => $articleDisplayNames,
            'tierMultipliers' => $priceSuggestions,
            'errors' => $errors,
            'old' => $old,
            'editingId' => $editingId,
            'existingItems' => $existingItems,
            'pricingSettings' => $pricingSettings,
            'laborArticles' => $laborArticles,
            'existingLaborItems' => $existingLaborItems,
            'existingFreeTextItems' => $existingFreeTextItems,
            'existingSectionTexts' => $existingSectionTexts,
            'isSmallBusinessPreview' => $isSmallBusinessPreview,
            'vatRatePercentPreview' => $vatRatePercentPreview,
            'selectedRentalId' => $selectedRentalId,
            'selectedRentalLabel' => $selectedRentalLabel,
        ], activeNav: 'invoices', title: $invoice !== null ? t('invoices.edit') : t('invoices.new'));
    }

    /** @return array<int, array{article_id: string, quantity: string, unit_price: string, line_note: string, sort_order: string, is_rental_item: string}> */
    private function itemsFromRequest(Request $request): array
    {
        $articleIds = $request->post['article_id'] ?? [];
        $quantities = $request->post['quantity'] ?? [];
        $unitPrices = $request->post['unit_price'] ?? [];
        $lineNotes = $request->post['line_note'] ?? [];
        $sortOrders = $request->post['sort_order'] ?? [];
        // Checkbox je Zeile (siehe invoices/form.php) — "1" wenn angehakt
        // (Mietposition, Default), fehlt im POST-Array ansonsten (Verkauf/
        // Sonstiges), siehe ValidatesInvoiceInput::validateInvoiceInput().
        $isRentalItems = $request->post['is_rental_item'] ?? [];

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
                'is_rental_item' => (string) ($isRentalItems[$index] ?? '0'),
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
    private function oldFromCommand(CreateInvoice|UpdateInvoice $command): array
    {
        return [
            'customer_id' => $command->customerId,
            'start_date' => $command->startDate,
            'end_date' => $command->endDate,
            'event_location' => $command->eventLocation,
            'buyer_reference' => $command->buyerReference,
            'note' => $command->note,
            'items' => $command->items,
            'accessory_flat_rate_enabled' => $command->accessoryFlatRateEnabled ? '1' : '',
            'discount_type' => $command->discountType ?? '',
            'discount_value' => $command->discountValue ?? '',
            'labor_items' => $command->laborItems,
            'free_text_items' => $command->freeTextItems,
            'section_texts' => $command->sectionTexts,
            'closing_text' => $command->closingText ?? '',
            'rental_id' => $command->rentalId ?? '',
            'flat_rate_enabled' => $command->flatRateEnabled ? '1' : '',
            'flat_rate_amount' => $command->flatRateAmount ?? '',
            'logistics_method' => $command->logisticsMethod ?? '',
            'logistics_date1' => $command->logisticsDate1 ?? '',
            'logistics_date2' => $command->logisticsDate2 ?? '',
        ];
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
