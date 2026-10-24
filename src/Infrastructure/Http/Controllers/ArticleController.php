<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Article\Command\AddArticleAccessory;
use App\Application\Article\Command\AddArticleImage;
use App\Application\Article\Command\AddFirmwareVersion;
use App\Application\Article\Command\AddInventoryNumber;
use App\Application\Article\Command\CreateArticle;
use App\Application\Article\Command\DeleteArticle;
use App\Application\Article\Command\DeleteArticleAccessory;
use App\Application\Article\Command\DeleteArticleImage;
use App\Application\Article\Command\DeleteFirmwareVersion;
use App\Application\Article\Command\DeleteInventoryNumber;
use App\Application\Article\Command\SetArticleActive;
use App\Application\Article\Command\SetArticleChannelEnabled;
use App\Application\Article\Command\SyncArticleChannel;
use App\Application\Article\Command\UpdateArticle;
use App\Application\Article\Command\UpdateArticleAccessory;
use App\Application\Article\Command\UpdateInventoryNumber;
use App\Application\Article\Query\GetArticle;
use App\Application\Article\Query\ListArticles;
use App\Application\Article\Query\ListArticlesForDisplay;
use App\Application\Category\Query\ListCategories;
use App\Application\Command\CommandBus;
use App\Application\Manufacturer\Query\ListManufacturers;
use App\Application\Query\QueryBus;
use App\Application\Receipt\Query\ListReceipts;
use App\Application\Rental\Query\GetArticleAvailability;
use App\Application\Rental\Query\ListRentalsForArticle;
use App\Application\Sale\Command\SellArticleFromStock;
use App\Application\Settings\Query\GetPriceTiers;
use App\Application\Supplier\Query\ListSuppliers;
use App\Domain\Article\ChannelSyncStatus;
use App\Domain\Article\SalesChannel;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\RentalPriceCalculator;
use App\Domain\Stock\SaleStockLotRepositoryInterface;
use App\Infrastructure\Csv\PriceListCsvExporter;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\MonthGridBuilder;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;
use App\Infrastructure\Pdf\PriceListPdfGenerator;

final class ArticleController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly LocalFileStorage $fileStorage,
        private readonly PriceListPdfGenerator $priceListPdfGenerator,
        private readonly PriceListCsvExporter $priceListCsvExporter,
        private readonly SaleStockLotRepositoryInterface $stockLots,
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    /** Exportiert die Preisliste (abrechenbare Artikel × Einsatztage-Staffelung) als PDF-Download. */
    public function exportPriceListPdf(Request $request): void
    {
        $articles = $this->billableArticlesForPriceList();
        $tiers = $this->queries->ask(new GetPriceTiers());

        $pdf = $this->priceListPdfGenerator->generate($articles, $tiers);

        Response::download($pdf, 'Preisliste.pdf', 'application/pdf');
    }

    /** Exportiert dieselbe Preisliste als CSV-Download (siehe exportPriceListPdf). */
    public function exportPriceListCsv(Request $request): void
    {
        $articles = $this->billableArticlesForPriceList();
        $tiers = $this->queries->ask(new GetPriceTiers());

        $csv = $this->priceListCsvExporter->generate($articles, $tiers);

        Response::download($csv, 'Preisliste.csv', 'text/csv; charset=UTF-8');
    }

    /** @return \App\Domain\Article\Article[] alphabetisch sortierte, aktive, abrechenbare Artikel */
    private function billableArticlesForPriceList(): array
    {
        $articles = $this->queries->ask(new ListArticles(onlyActive: true))->items;

        $billable = array_values(array_filter($articles, static fn ($article) => $article->isBillable));
        usort($billable, static fn ($a, $b) => $a->name <=> $b->name);

        return $billable;
    }

    /**
     * Liefert ein Artikelbild aus. Bilder liegen bewusst außerhalb von public/,
     * damit der Zugriff konsistent über die Anwendung läuft (siehe auch RMA-Dokumente).
     */
    public function showImage(Request $request): void
    {
        $articleId = $request->param('id');
        $filename = $request->param('filename');
        $imagePath = $articleId . '/' . $filename;

        try {
            $detail = $this->queries->ask(new GetArticle($articleId));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $match = null;
        foreach ($detail->article->images as $image) {
            if ($image->filePath === $imagePath) {
                $match = $image;
            }
        }

        if ($match === null) {
            Response::notFound('Bild wurde nicht gefunden.');

            return;
        }

        $absolutePath = $this->fileStorage->absolutePath('articles', $match->filePath);
        $mimeType = function_exists('mime_content_type') ? (mime_content_type($absolutePath) ?: 'application/octet-stream') : 'application/octet-stream';

        Response::file($absolutePath, basename($match->filePath), $mimeType);
    }

    public function index(Request $request): void
    {
        $search = $request->query('q');
        $manufacturerId = $this->nullIfEmpty($request->query('hersteller'));
        $categoryId = $this->nullIfEmpty($request->query('kategorie'));
        $page = Pagination::page($request);
        $pageSize = Pagination::pageSize($request, CurrentUser::get());

        // ListArticlesForDisplay ist der zentrale Use Case für "Artikelliste
        // mit aufgelöstem Hersteller/Kategorie" — dieselbe Anlaufstelle, die
        // auch die REST-API (ArticleApiController::index) nutzt.
        $result = $this->queries->ask(new ListArticlesForDisplay($search, $manufacturerId, $categoryId, $page, $pageSize));
        // Volle Listen weiterhin separat für die Filter-Dropdowns (nicht nur
        // die auf der aktuellen Seite vorkommenden Hersteller/Kategorien).
        $manufacturers = $this->queries->ask(new ListManufacturers());
        $categories = $this->queries->ask(new ListCategories());

        $viewData = [
            'articles' => $result->items,
            'manufacturers' => $manufacturers,
            'categories' => $categories,
            'search' => $search ?? '',
            'selectedManufacturerId' => $manufacturerId,
            'selectedCategoryId' => $categoryId,
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'totalCount' => $result->totalCount,
            'totalPages' => $result->totalPages(),
            'paginationBaseUrl' => '/artikel',
            'paginationQuery' => array_filter([
                'q' => $search ?? '',
                'hersteller' => $manufacturerId ?? '',
                'kategorie' => $categoryId ?? '',
            ], static fn ($value) => $value !== ''),
        ];

        if (Pagination::isAjax($request)) {
            echo View::render('articles/_table', $viewData);

            return;
        }

        echo View::page('articles/index', $viewData, activeNav: 'articles', title: t('articles.title'));
    }

    public function create(Request $request): void
    {
        $this->renderForm(null, [], []);
    }

    public function store(Request $request): void
    {
        $command = new CreateArticle(
            name: $request->input('name', '') ?? '',
            manufacturerId: $request->input('manufacturer_id', '') ?? '',
            prefix: $request->input('prefix', '') ?? '',
            categoryId: $this->nullIfEmpty($request->input('category_id')),
            description: $request->input('description'),
            weightValue: $this->nullIfEmpty($request->input('weight_value')),
            weightUnit: $this->nullIfEmpty($request->input('weight_unit')),
            dimensions: $request->input('dimensions'),
            purchasePrice: $this->nullIfEmpty($request->input('purchase_price')),
            requiresElectricalInspection: $request->input('requires_electrical_inspection') !== null,
            isBillable: $request->input('is_billable') !== null,
            dailyRentalPrice: $this->nullIfEmpty($request->input('daily_rental_price')),
            is19Inch: $request->input('is_19_inch') !== null,
            heightUnits: $this->nullIfEmpty($request->input('height_units')),
            installDepthCm: $this->nullIfEmpty($request->input('install_depth_cm')),
            hasFirmware: $request->input('has_firmware') !== null,
        );

        try {
            $articleId = $this->commands->dispatch($command);
            Flash::success(t('articles.flash.created'));
            Response::redirect('/artikel/' . $articleId);
        } catch (ValidationException $e) {
            $this->renderForm(null, $e->errors(), $this->oldFromCommand($command));
        }
    }

    public function show(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetArticle($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $editInventoryId = $this->nullIfEmpty($request->query('edit_inventory'));
        $editAccessoryId = $this->nullIfEmpty($request->query('edit_accessory'));
        $suppliers = $this->queries->ask(new ListSuppliers())->items;
        // Für die Beleg-Auswahl beim Anlegen/Bearbeiten einer Inventurnummer werden
        // bewusst alle Belege geladen (kein Filter nötig; die Liste ist für den
        // typischen Einsatzzweck überschaubar).
        $receipts = $this->queries->ask(new ListReceipts(page: 1, pageSize: 10000))->items;

        $rentalEntries = $this->queries->ask(new ListRentalsForArticle($id));
        $today = new \DateTimeImmutable('today');
        $upcomingRentals = array_values(array_filter($rentalEntries, fn ($entry) => $entry->rental->endDate >= $today));
        $pastRentals = array_values(array_filter($rentalEntries, fn ($entry) => $entry->rental->endDate < $today));
        // findByArticle liefert bereits neueste zuerst (Startdatum absteigend);
        // für "kommende Vermietungen" ist die nächstliegende zuerst sinnvoller.
        usort($upcomingRentals, fn ($a, $b) => $a->rental->startDate <=> $b->rental->startDate);

        // Verfügbarkeits-Ampel: eigener Monats-Navigationsparameter, getrennt
        // von "edit_inventory", damit beide unabhängig voneinander in der URL stehen können.
        $availabilityReference = MonthGridBuilder::parseMonth($request->query('verf_monat'));
        [$availabilityMonth, , $availabilityGridStart, $availabilityGridEnd] = MonthGridBuilder::bounds($availabilityReference);
        $availabilityWeeks = MonthGridBuilder::weeks($availabilityGridStart, $availabilityGridEnd);
        $availability = $this->queries->ask(new GetArticleAvailability(
            $id,
            $availabilityGridStart->format('Y-m-d'),
            $availabilityGridEnd->format('Y-m-d'),
        ));

        $rentalPricesByDay = null;
        if ($detail->article->isBillable && $detail->article->dailyRentalPrice !== null) {
            $priceTiers = $this->queries->ask(new GetPriceTiers());
            $rentalPricesByDay = RentalPriceCalculator::calculate($detail->article->dailyRentalPrice, $priceTiers);
        }

        // Reiner Verkaufsbestand (siehe App\Domain\Stock\SaleStockLot) — ganz
        // bewusst NICHT dasselbe wie die Inventurnummern-Liste oben: diese
        // Exemplare haben nie eine eigene Inventurnummer und gelangen nie in
        // den Vermietpool (siehe App\Domain\Purchase\PurchaseTargetType).
        $saleStockQuantity = $this->stockLots->sumRemainingByArticle($id);
        $saleStockLots = $this->stockLots->findByArticle($id);
        $saleCustomers = $this->customers->findAll();

        echo View::page('articles/show', [
            'detail' => $detail,
            'editInventoryId' => $editInventoryId,
            'editAccessoryId' => $editAccessoryId,
            'suppliers' => $suppliers,
            'receipts' => $receipts,
            'upcomingRentals' => $upcomingRentals,
            'pastRentals' => $pastRentals,
            'availabilityWeeks' => $availabilityWeeks,
            'availabilityMonth' => $availabilityMonth,
            'availability' => $availability,
            'availabilityPrevMonth' => $availabilityMonth->modify('-1 month')->format('Y-m'),
            'availabilityNextMonth' => $availabilityMonth->modify('+1 month')->format('Y-m'),
            'rentalPricesByDay' => $rentalPricesByDay,
            'saleStockQuantity' => $saleStockQuantity,
            'saleStockLots' => $saleStockLots,
            'saleCustomers' => $saleCustomers,
            'saleStockErrors' => [],
            'errors' => [],
            'old' => [],
        ], activeNav: 'articles', title: t('articles.title') . ': ' . $detail->article->name);
    }

    /** Verkauft eine Menge aus dem reinen Verkaufsbestand dieses Artikels (siehe SellArticleFromStockHandler) — legt dabei automatisch eine Rechnung (Entwurf) an. */
    public function sellFromStock(Request $request): void
    {
        $id = $request->param('id');
        $customerId = $request->input('customer_id', '') ?? '';
        $quantity = $request->input('quantity', '') ?? '';
        $salePrice = $request->input('sale_price', '') ?? '';
        $shippingAmount = $request->input('shipping_amount', '') ?? '';
        $note = $request->input('note', '') ?? '';

        try {
            $result = $this->commands->dispatch(new SellArticleFromStock(
                articleId: $id,
                quantity: $quantity,
                customerId: $customerId !== '' ? $customerId : null,
                salePrice: $salePrice,
                shippingAmount: $shippingAmount !== '' ? $shippingAmount : null,
                note: $note !== '' ? $note : null,
            ));

            Flash::success(t('articles.sell_stock.flash.success'));
            Response::redirect('/rechnungen/' . $result['invoiceId']);

            return;
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        }

        Response::redirect('/artikel/' . $id);
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetArticle($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $this->renderForm($detail->article, [], [], $detail->article->id);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        $command = new UpdateArticle(
            id: $id,
            name: $request->input('name', '') ?? '',
            manufacturerId: $request->input('manufacturer_id', '') ?? '',
            prefix: $request->input('prefix', '') ?? '',
            categoryId: $this->nullIfEmpty($request->input('category_id')),
            description: $request->input('description'),
            weightValue: $this->nullIfEmpty($request->input('weight_value')),
            weightUnit: $this->nullIfEmpty($request->input('weight_unit')),
            dimensions: $request->input('dimensions'),
            purchasePrice: $this->nullIfEmpty($request->input('purchase_price')),
            requiresElectricalInspection: $request->input('requires_electrical_inspection') !== null,
            isBillable: $request->input('is_billable') !== null,
            dailyRentalPrice: $this->nullIfEmpty($request->input('daily_rental_price')),
            is19Inch: $request->input('is_19_inch') !== null,
            heightUnits: $this->nullIfEmpty($request->input('height_units')),
            installDepthCm: $this->nullIfEmpty($request->input('install_depth_cm')),
            hasFirmware: $request->input('has_firmware') !== null,
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('articles.flash.updated'));
            Response::redirect('/artikel/' . $id);
        } catch (ValidationException $e) {
            $detail = $this->queries->ask(new GetArticle($id));
            $this->renderForm($detail->article, $e->errors(), $this->oldFromCommand($command), $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteArticle($id));
            Flash::success(t('articles.flash.deleted'));
            Response::redirect('/artikel');
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function activate(Request $request): void
    {
        $this->setActive($request, true);
    }

    public function deactivate(Request $request): void
    {
        $this->setActive($request, false);
    }

    private function setActive(Request $request, bool $isActive): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new SetArticleActive($id, $isActive));
            Flash::success($isActive ? t('articles.flash.activated') : t('articles.flash.deactivated'));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/artikel/' . $id);
    }

    public function uploadImage(Request $request): void
    {
        $articleId = $request->param('id');
        $file = $request->file('image');

        if ($file !== null) {
            try {
                $this->commands->dispatch(new AddArticleImage($articleId, $file));
            } catch (ValidationException $e) {
                Flash::error(implode(' ', $e->errors()));
            } catch (NotFoundException $e) {
                Response::notFound($e->getMessage());

                return;
            }
        }

        Response::redirect('/artikel/' . $articleId);
    }

    public function deleteImage(Request $request): void
    {
        $imageId = $request->param('imageId');
        $articleId = $request->input('article_id', '') ?? '';

        try {
            $this->commands->dispatch(new DeleteArticleImage($imageId));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/artikel/' . $articleId);
    }

    public function enableChannel(Request $request): void
    {
        $this->setChannelEnabled($request, true);
    }

    public function disableChannel(Request $request): void
    {
        $this->setChannelEnabled($request, false);
    }

    private function setChannelEnabled(Request $request, bool $enabled): void
    {
        $articleId = $request->param('id');
        $channel = SalesChannel::tryFrom($request->param('channel'));

        if ($channel === null) {
            Response::notFound(t('articles.show.channels.unknown_channel'));

            return;
        }

        try {
            /** @var \App\Domain\Article\ArticleChannelListing $listing */
            $listing = $this->commands->dispatch(new SetArticleChannelEnabled($articleId, $channel, $enabled));
            if ($enabled && $listing->status === ChannelSyncStatus::Failed) {
                Flash::error(t('articles.show.channels.flash.sync_failed', ['channel' => $channel->label(), 'error' => (string) $listing->lastError]));
            } elseif ($enabled) {
                Flash::success(t('articles.show.channels.flash.enabled', ['channel' => $channel->label()]));
            } else {
                Flash::success(t('articles.show.channels.flash.disabled', ['channel' => $channel->label()]));
            }
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/artikel/' . $articleId);
    }

    public function syncChannel(Request $request): void
    {
        $articleId = $request->param('id');
        $channel = SalesChannel::tryFrom($request->param('channel'));

        if ($channel === null) {
            Response::notFound(t('articles.show.channels.unknown_channel'));

            return;
        }

        try {
            /** @var \App\Domain\Article\ArticleChannelListing $listing */
            $listing = $this->commands->dispatch(new SyncArticleChannel($articleId, $channel));
            if ($listing->status === ChannelSyncStatus::Failed) {
                Flash::error(t('articles.show.channels.flash.sync_failed', ['channel' => $channel->label(), 'error' => (string) $listing->lastError]));
            } else {
                Flash::success(t('articles.show.channels.flash.enabled', ['channel' => $channel->label()]));
            }
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/artikel/' . $articleId);
    }

    public function addInventoryNumber(Request $request): void
    {
        $articleId = $request->param('id');
        $acquisitionDate = $this->nullIfEmpty($request->input('acquisition_date'));
        $supplierId = $this->nullIfEmpty($request->input('supplier_id'));
        $receiptId = $this->nullIfEmpty($request->input('receipt_id'));
        $purchasePriceOverride = $this->nullIfEmpty($request->input('purchase_price'));
        $storageLocation = $this->nullIfEmpty($request->input('storage_location'));
        $installedFirmwareVersionId = $this->nullIfEmpty($request->input('installed_firmware_version_id'));

        try {
            $this->commands->dispatch(new AddInventoryNumber($articleId, $acquisitionDate, $supplierId, $receiptId, $purchasePriceOverride, $storageLocation, $installedFirmwareVersionId));
            Flash::success('Inventurnummer wurde angelegt.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/artikel/' . $articleId);
    }

    public function updateInventoryNumber(Request $request): void
    {
        $inventoryNumberId = $request->param('id');
        $articleId = $request->input('article_id', '') ?? '';
        $acquisitionDate = $this->nullIfEmpty($request->input('acquisition_date'));
        $supplierId = $this->nullIfEmpty($request->input('supplier_id'));
        $receiptId = $this->nullIfEmpty($request->input('receipt_id'));
        $purchasePriceOverride = $this->nullIfEmpty($request->input('purchase_price'));
        $storageLocation = $this->nullIfEmpty($request->input('storage_location'));
        $installedFirmwareVersionId = $this->nullIfEmpty($request->input('installed_firmware_version_id'));

        try {
            $this->commands->dispatch(new UpdateInventoryNumber($inventoryNumberId, $acquisitionDate, $supplierId, $receiptId, $purchasePriceOverride, $storageLocation, $installedFirmwareVersionId));
            Flash::success('Inventurnummer wurde aktualisiert.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
            Response::redirect('/artikel/' . $articleId . '?edit_inventory=' . $inventoryNumberId);

            return;
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/artikel/' . $articleId);
    }

    public function deleteInventoryNumber(Request $request): void
    {
        $inventoryNumberId = $request->param('id');
        $articleId = $request->input('article_id', '') ?? '';

        try {
            $this->commands->dispatch(new DeleteInventoryNumber($inventoryNumberId));
            Flash::success('Inventurnummer wurde gelöscht.');
        } catch (ConflictException|NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/artikel/' . $articleId);
    }

    public function addFirmwareVersion(Request $request): void
    {
        $articleId = $request->param('id');
        $version = $request->input('version', '') ?? '';
        $releasedAt = $this->nullIfEmpty($request->input('released_at'));

        try {
            $this->commands->dispatch(new AddFirmwareVersion($articleId, $version, $releasedAt));
            Flash::success('Firmware-Version wurde angelegt.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/artikel/' . $articleId);
    }

    public function deleteFirmwareVersion(Request $request): void
    {
        $firmwareVersionId = $request->param('id');
        $articleId = $request->input('article_id', '') ?? '';

        try {
            $this->commands->dispatch(new DeleteFirmwareVersion($firmwareVersionId));
            Flash::success('Firmware-Version wurde gelöscht.');
        } catch (ConflictException|NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/artikel/' . $articleId);
    }

    public function addAccessory(Request $request): void
    {
        $articleId = $request->param('id');
        $quantity = $request->input('quantity', '') ?? '';
        $description = $request->input('description', '') ?? '';

        try {
            $this->commands->dispatch(new AddArticleAccessory($articleId, $quantity, $description));
            Flash::success('Zubehör wurde hinzugefügt.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/artikel/' . $articleId);
    }

    public function updateAccessory(Request $request): void
    {
        $accessoryId = $request->param('id');
        $articleId = $request->input('article_id', '') ?? '';
        $quantity = $request->input('quantity', '') ?? '';
        $description = $request->input('description', '') ?? '';

        try {
            $this->commands->dispatch(new UpdateArticleAccessory($accessoryId, $quantity, $description));
            Flash::success('Zubehör wurde aktualisiert.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
            Response::redirect('/artikel/' . $articleId . '?edit_accessory=' . $accessoryId);

            return;
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/artikel/' . $articleId);
    }

    public function deleteAccessory(Request $request): void
    {
        $accessoryId = $request->param('id');
        $articleId = $request->input('article_id', '') ?? '';

        try {
            $this->commands->dispatch(new DeleteArticleAccessory($accessoryId));
            Flash::success('Zubehör wurde gelöscht.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/artikel/' . $articleId);
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderForm(?\App\Domain\Article\Article $article, array $errors, array $old, ?string $editingId = null): void
    {
        $manufacturers = $this->queries->ask(new ListManufacturers());
        $categories = $this->queries->ask(new ListCategories());

        echo View::page('articles/form', [
            'article' => $article,
            'manufacturers' => $manufacturers,
            'categories' => $categories,
            'errors' => $errors,
            'old' => $old,
            'editingId' => $editingId,
        ], activeNav: 'articles', title: $article !== null ? t('articles.edit') : t('articles.new'));
    }

    /** @return array<string, mixed> */
    private function oldFromCommand(CreateArticle|UpdateArticle $command): array
    {
        return [
            'name' => $command->name,
            'manufacturer_id' => $command->manufacturerId,
            'prefix' => $command->prefix,
            'category_id' => $command->categoryId,
            'description' => $command->description,
            'weight_value' => $command->weightValue,
            'weight_unit' => $command->weightUnit,
            'dimensions' => $command->dimensions,
            'purchase_price' => $command->purchasePrice,
            'requires_electrical_inspection' => $command->requiresElectricalInspection,
            'is_billable' => $command->isBillable,
            'daily_rental_price' => $command->dailyRentalPrice,
            'is_19_inch' => $command->is19Inch,
            'height_units' => $command->heightUnits,
            'install_depth_cm' => $command->installDepthCm,
            'has_firmware' => $command->hasFirmware,
        ];
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
