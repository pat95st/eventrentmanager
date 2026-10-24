<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Article\Query\ListArticles;
use App\Application\Command\CommandBus;
use App\Application\Customer\Query\ListCustomers;
use App\Application\Invoice\Command\CreateInvoiceFromRental;
use App\Application\Query\QueryBus;
use App\Application\Rental\Command\AddRentalItem;
use App\Application\Rental\Command\ConfirmRentalItemAccessories;
use App\Application\Rental\Command\CreateRental;
use App\Application\Rental\Command\DeleteRental;
use App\Application\Rental\Command\DeleteRentalItem;
use App\Application\Rental\Command\EmailPackingListPdf;
use App\Application\Rental\Command\EmailRentalCustomerChecklist;
use App\Application\Rental\Command\IssueRentalCustomerChecklistToken;
use App\Application\Rental\Command\IssueRentalItem;
use App\Application\Rental\Command\ReturnRentalItem;
use App\Application\Rental\Command\UndoReturnRentalItem;
use App\Application\Rental\Command\UnissueRentalItem;
use App\Application\Rental\Command\UpdateRental;
use App\Application\Rental\Command\UpdateRentalItem;
use App\Application\Rental\Command\UpdateRentalLogistics;
use App\Application\Rental\Query\GetRental;
use App\Application\Rental\Query\ListRentals;
use App\Application\Rental\RentalCustomerChecklistUrlBuilder;
use App\Application\Settings\Query\GetCompanySettings;
use App\Application\Vehicle\Command\AssignRentalVehicleLoad;
use App\Application\Vehicle\Command\RemoveRentalVehicleLoad;
use App\Application\Vehicle\Command\RemoveRentalVehicleTow;
use App\Application\Vehicle\Command\SetRentalVehicleTow;
use App\Application\Vehicle\Query\GetRentalLoadingList;
use App\Application\Venue\Query\GetVenuesNeedDistributorFlags;
use App\Application\Voucher\Command\RedeemVoucherForRental;
use App\Application\Voucher\Command\RemoveVoucherRedemption;
use App\Application\Voucher\Query\GetVoucher;
use App\Application\Warehouse\Query\ListWarehouses;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalCustomerChecklistTokenRepositoryInterface;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Domain\Voucher\VoucherRedemptionRepositoryInterface;
use App\Domain\Warehouse\WarehouseRepositoryInterface;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;
use App\Infrastructure\Mail\MailerException;
use App\Infrastructure\Pdf\PackingListPdfGenerator;
use App\Shared\Prefix;

final class RentalController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly RentalIssuedItemRepositoryInterface $issuedItems,
        private readonly VenueRepositoryInterface $venues,
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly PackingListPdfGenerator $packingListPdfGenerator,
        private readonly RentalCustomerChecklistTokenRepositoryInterface $customerChecklistTokens,
        private readonly RentalCustomerChecklistUrlBuilder $customerChecklistUrlBuilder,
        private readonly VoucherRedemptionRepositoryInterface $voucherRedemptions,
    ) {
    }

    public function index(Request $request): void
    {
        $search = $request->query('q');
        $customerId = $this->nullIfEmpty($request->query('kunde'));
        $page = Pagination::page($request);
        $pageSize = Pagination::pageSize($request, CurrentUser::get());

        $result = $this->queries->ask(new ListRentals($search, $customerId, $page, $pageSize));
        $customers = $this->queries->ask(new ListCustomers())->items;

        $viewData = [
            'rentals' => $result->items,
            'customers' => $customers,
            'search' => $search ?? '',
            'selectedCustomerId' => $customerId,
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'totalCount' => $result->totalCount,
            'totalPages' => $result->totalPages(),
            'paginationBaseUrl' => '/veranstaltungen',
            'paginationQuery' => array_filter([
                'q' => $search ?? '',
                'kunde' => $customerId ?? '',
            ], static fn ($value) => $value !== ''),
        ];

        if (Pagination::isAjax($request)) {
            echo View::render('rentals/_table', $viewData);

            return;
        }

        echo View::page('rentals/index', $viewData, activeNav: 'rentals', title: t('rentals.title'));
    }

    public function create(Request $request): void
    {
        $this->renderForm(null, [], []);
    }

    public function store(Request $request): void
    {
        $command = new CreateRental(
            customerId: $request->input('customer_id', '') ?? '',
            startDate: $request->input('start_date', '') ?? '',
            endDate: $request->input('end_date', '') ?? '',
            eventName: $request->input('event_name', '') ?? '',
            eventLocation: $request->input('event_location', '') ?? '',
            deploymentDays: $request->input('deployment_days', '') ?? '',
        );

        try {
            $rentalId = $this->commands->dispatch($command);
            Flash::success(t('rentals.flash.created'));
            Response::redirect('/veranstaltungen/' . $rentalId);
        } catch (ValidationException $e) {
            $this->renderForm(null, $e->errors(), $this->oldFromCommand($command));
        }
    }

    public function show(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetRental($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $articles = $this->queries->ask(new ListArticles(onlyActive: true))->items;
        $venue = $detail->rental->venueId !== null ? $this->venues->findById($detail->rental->venueId) : null;
        $warehouses = $this->queries->ask(new ListWarehouses())->items;
        $companySettings = $this->queries->ask(new GetCompanySettings());
        $companyAddress = trim(implode(', ', array_filter([
            $companySettings->street,
            trim(($companySettings->postalCode ?? '') . ' ' . ($companySettings->city ?? '')),
        ], static fn ($part) => $part !== null && trim($part) !== '')));

        // Kunden-Checkliste: bereits erzeugten Link (falls vorhanden) für die
        // Anzeige/zum Kopieren auflösen. Fehlt die Basis-URL (Einstellungen >
        // Integrationen), bleibt $customerChecklistUrl null und die View
        // zeigt stattdessen den Hinweis aus $customerChecklistUrlError an —
        // kein harter Fehler auf der sonst funktionierenden Detailseite.
        $customerChecklistToken = $this->customerChecklistTokens->findByRentalId($id);
        $customerChecklistUrl = null;
        $customerChecklistUrlError = null;
        if ($customerChecklistToken !== null) {
            try {
                $customerChecklistUrl = $this->customerChecklistUrlBuilder->url($customerChecklistToken->token);
            } catch (ValidationException $e) {
                $customerChecklistUrlError = implode(' ', $e->errors());
            }
        }

        $voucherRedemption = $this->voucherRedemptions->findByRentalId($id);
        $voucher = $voucherRedemption !== null ? $this->queries->ask(new GetVoucher($voucherRedemption->voucherId)) : null;

        echo View::page('rentals/show', [
            'detail' => $detail,
            'articles' => $articles,
            'venue' => $venue,
            'warehouses' => $warehouses,
            'companyAddress' => $companyAddress,
            'customerChecklistUrl' => $customerChecklistUrl,
            'customerChecklistUrlError' => $customerChecklistUrlError,
            'voucherRedemption' => $voucherRedemption,
            'voucher' => $voucher,
        ], activeNav: 'rentals', title: t('rentals.page_title.detail', ['event' => $detail->rental->eventName]));
    }

    /** Ordnet einen per Code eingegebenen Gutschein dieser Vermietung zu (siehe rentals/show.php, Box "Gutschein"). */
    public function redeemVoucher(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new RedeemVoucherForRental($id, $request->input('code', '') ?? ''));
            Flash::success('Gutschein wurde eingelöst.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        }

        Response::redirect('/veranstaltungen/' . $id);
    }

    /** Hebt die Zuordnung eines Gutscheins zu dieser Vermietung wieder auf — der Gutschein selbst bleibt erhalten und kann erneut eingelöst werden. */
    public function removeVoucherRedemption(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new RemoveVoucherRedemption($id));
            Flash::success('Die Gutschein-Zuordnung wurde aufgehoben.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/veranstaltungen/' . $id);
    }

    /** Kunden-Checkliste: Link erzeugen (falls noch keiner existiert) bzw. einen bestehenden unverändert anzeigen. */
    public function issueCustomerChecklistLink(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new IssueRentalCustomerChecklistToken($id, forceNew: false));
            Flash::success(t('rentals.show.customer_checklist.flash.link_created'));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/veranstaltungen/' . $id);
    }

    /** Kunden-Checkliste: einen NEUEN Link erzeugen — macht einen ggf. bereits an den Kunden herausgegebenen Link ungültig. */
    public function regenerateCustomerChecklistLink(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new IssueRentalCustomerChecklistToken($id, forceNew: true));
            Flash::success(t('rentals.show.customer_checklist.flash.link_regenerated'));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/veranstaltungen/' . $id);
    }

    /** Kunden-Checkliste: Link sofort per E-Mail an die hinterlegte Kunden-Adresse verschicken. */
    public function emailCustomerChecklistLink(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetRental($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $recipient = $detail->customer->email;
        if ($recipient === null || trim($recipient) === '') {
            Flash::error(t('rentals.show.customer_checklist.flash.no_customer_email'));
            Response::redirect('/veranstaltungen/' . $id);

            return;
        }

        try {
            $this->commands->dispatch(new EmailRentalCustomerChecklist($id, $recipient));
            Flash::success(t('rentals.show.customer_checklist.flash.emailed', ['recipient' => $recipient]));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (MailerException $e) {
            Flash::error(t('rentals.flash.email_failed', ['error' => $e->getMessage()]));
        }

        Response::redirect('/veranstaltungen/' . $id);
    }

    /** Aktualisiert die optionale Transport-/Logistik-Zuordnung (Lagerorte + Termine, siehe UpdateRentalLogisticsHandler) auf der Detailseite. */
    public function updateLogistics(Request $request): void
    {
        $id = $request->param('id');

        $command = new UpdateRentalLogistics(
            rentalId: $id,
            pickupWarehouseId: $request->input('pickup_warehouse_id'),
            pickupAt: $request->input('pickup_at'),
            returnWarehouseId: $request->input('return_warehouse_id'),
            returnAt: $request->input('return_at'),
            transportNote: $request->input('transport_note'),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('rentals.logistics.flash.updated'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/veranstaltungen/' . $id);
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetRental($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $this->renderForm($detail->rental, [], [], $detail->rental->id);
    }

    /**
     * Beladungsliste: ordnet die bereits ausgegebenen Posten (konkrete
     * Inventarnummern) dieser Veranstaltung einem Fahrzeug zu und zeigt je
     * Fahrzeug das Gesamtgewicht sowie einen Überladungs-Hinweis an (siehe
     * GetRentalLoadingListHandler/VehicleLoadCalculator).
     */
    public function loadingList(Request $request): void
    {
        $id = $request->param('id');

        try {
            $loadingList = $this->queries->ask(new GetRentalLoadingList($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        echo View::page('rentals/loading-list', [
            'loadingList' => $loadingList,
        ], activeNav: 'rentals', title: t('rentals.page_title.loading_list', ['event' => $loadingList->rental->eventName]));
    }

    public function assignVehicleLoad(Request $request): void
    {
        $rentalId = $request->param('id');
        $rentalIssuedItemId = $request->input('rental_issued_item_id', '') ?? '';
        $vehicleId = $request->input('vehicle_id', '') ?? '';

        try {
            $this->commands->dispatch(new AssignRentalVehicleLoad($rentalIssuedItemId, $vehicleId));
            Flash::success(t('rentals.loading_list.flash.assigned'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/veranstaltungen/' . $rentalId . '/beladung');
    }

    public function removeVehicleLoad(Request $request): void
    {
        $rentalId = $request->param('id');
        $rentalIssuedItemId = $request->input('rental_issued_item_id', '') ?? '';

        try {
            $this->commands->dispatch(new RemoveRentalVehicleLoad($rentalIssuedItemId));
            Flash::success(t('rentals.loading_list.flash.unassigned'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/veranstaltungen/' . $rentalId . '/beladung');
    }

    public function setVehicleTow(Request $request): void
    {
        $rentalId = $request->param('id');
        $towedVehicleId = $request->input('towed_vehicle_id', '') ?? '';
        $towingVehicleId = $request->input('towing_vehicle_id', '') ?? '';

        try {
            $this->commands->dispatch(new SetRentalVehicleTow($rentalId, $towedVehicleId, $towingVehicleId));
            Flash::success(t('rentals.loading_list.flash.tow_assigned'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/veranstaltungen/' . $rentalId . '/beladung');
    }

    public function removeVehicleTow(Request $request): void
    {
        $rentalId = $request->param('id');
        $towedVehicleId = $request->input('towed_vehicle_id', '') ?? '';

        $this->commands->dispatch(new RemoveRentalVehicleTow($rentalId, $towedVehicleId));
        Flash::success(t('rentals.loading_list.flash.tow_removed'));

        Response::redirect('/veranstaltungen/' . $rentalId . '/beladung');
    }

    /** Erstellt aus dieser Veranstaltung eine neue Entwurfs-Rechnung (siehe CreateInvoiceFromRentalHandler) und leitet auf deren Bearbeitungsseite weiter, damit der Benutzer die vorgeschlagenen Preise vor dem Versenden prüfen/anpassen kann. */
    public function createInvoice(Request $request): void
    {
        $id = $request->param('id');

        try {
            $invoiceId = $this->commands->dispatch(new CreateInvoiceFromRental($id));
            Flash::success(t('rentals.show.invoice_created'));
            Response::redirect('/rechnungen/' . $invoiceId . '/bearbeiten');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/veranstaltungen/' . $id);
    }

    /** Exportiert die Packliste (geplante Artikel inkl. Menge/Ausgabestand) dieser Veranstaltung als PDF-Download. */
    public function exportPackingListPdf(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetRental($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $companySettings = $this->queries->ask(new GetCompanySettings());
        $pdf = $this->packingListPdfGenerator->generate($detail, $companySettings);

        $filename = 'Packliste-' . $this->sanitizeFilename($detail->rental->eventName !== '' ? $detail->rental->eventName : $detail->rental->id) . '.pdf';

        Response::download($pdf, $filename, 'application/pdf');
    }

    /** Verschickt dieselbe Packliste (siehe exportPackingListPdf) per E-Mail als PDF-Anhang statt als Download. */
    public function emailPackingListPdf(Request $request): void
    {
        $id = $request->param('id');
        $recipient = trim($request->input('recipient_email', '') ?? '');
        $note = trim($request->input('note', '') ?? '');

        try {
            $this->commands->dispatch(new EmailPackingListPdf($id, $recipient, $note !== '' ? $note : null));
            Flash::success(t('rentals.flash.pdf_emailed', ['recipient' => $recipient]));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (MailerException $e) {
            Flash::error(t('rentals.flash.email_failed', ['error' => $e->getMessage()]));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/veranstaltungen/' . $id);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        $command = new UpdateRental(
            id: $id,
            customerId: $request->input('customer_id', '') ?? '',
            startDate: $request->input('start_date', '') ?? '',
            endDate: $request->input('end_date', '') ?? '',
            eventName: $request->input('event_name', '') ?? '',
            eventLocation: $request->input('event_location', '') ?? '',
            deploymentDays: $request->input('deployment_days', '') ?? '',
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('rentals.flash.updated'));
            Response::redirect('/veranstaltungen/' . $id);
        } catch (ValidationException $e) {
            $detail = $this->queries->ask(new GetRental($id));
            $this->renderForm($detail->rental, $e->errors(), $this->oldFromCommand($command), $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteRental($id));
            Flash::success(t('rentals.flash.deleted'));
            Response::redirect('/veranstaltungen');
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function addItem(Request $request): void
    {
        $rentalId = $request->param('id');
        $articleId = $request->input('article_id', '') ?? '';
        $quantity = $request->input('quantity', '') ?? '';

        try {
            $this->commands->dispatch(new AddRentalItem($rentalId, $articleId, $quantity));
            Flash::success(t('rentals.flash.item_added_full'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/veranstaltungen/' . $rentalId);
    }

    public function updateItem(Request $request): void
    {
        $itemId = $request->param('id');
        $rentalId = $request->input('rental_id', '') ?? '';
        $quantity = $request->input('quantity', '') ?? '';

        try {
            $this->commands->dispatch(new UpdateRentalItem($itemId, $quantity));
            Flash::success(t('rentals.flash.item_quantity_updated'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/veranstaltungen/' . $rentalId);
    }

    public function deleteItem(Request $request): void
    {
        $itemId = $request->param('id');
        $rentalId = $request->input('rental_id', '') ?? '';

        try {
            $this->commands->dispatch(new DeleteRentalItem($itemId));
            Flash::success(t('rentals.flash.item_removed_full'));
        } catch (ConflictException|NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/veranstaltungen/' . $rentalId);
    }

    /** Ausgabe-Checkliste: tatsächlich ausgegebenes Material anhand der Inventarnummer erfassen. */
    public function checklist(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetRental($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        // Für jeden geplanten Artikel die verfügbaren (noch nicht in dieser
        // Veranstaltung ausgegebenen) Inventarnummern zusammenstellen.
        $availableByArticle = [];
        foreach ($detail->items as $entry) {
            $issuedIds = array_map(
                fn ($issued) => $issued->inventoryNumber->id,
                array_filter($detail->issuedItems, fn ($issued) => $issued->article->id === $entry->article->id)
            );
            $all = $this->inventoryNumbers->findActiveByArticle($entry->article->id);
            $availableByArticle[$entry->article->id] = array_values(array_filter(
                $all,
                fn ($inv) => !in_array($inv->id, $issuedIds, true)
            ));
        }

        echo View::page('rentals/checklist', [
            'detail' => $detail,
            'availableByArticle' => $availableByArticle,
        ], activeNav: 'rentals', title: t('rentals.page_title.checklist', ['event' => $detail->rental->eventName]));
    }

    public function issueItem(Request $request): void
    {
        $rentalId = $request->param('id');
        $inventoryNumberId = $request->input('inventory_number_id', '') ?? '';
        // Barcode-/Handscanner-Workflow: statt der Dropdown-Auswahl kann die
        // Inventarnummer direkt als Text gescannt/eingetippt werden (siehe
        // das separate Scan-Eingabefeld oben auf der Ausgabe-Checkliste).
        $scannedNumber = trim($request->input('number', '') ?? '');

        if ($inventoryNumberId === '' && $scannedNumber !== '') {
            $normalized = Prefix::normalize($scannedNumber);
            $inventoryNumber = $this->inventoryNumbers->findByNumber($normalized);

            if ($inventoryNumber === null) {
                Flash::error(t('rentals.flash.inventory_number_not_found', ['number' => $scannedNumber]));
                Response::redirect('/veranstaltungen/' . $rentalId . '/ausgabe');

                return;
            }

            $inventoryNumberId = $inventoryNumber->id;
        }

        try {
            $this->commands->dispatch(new IssueRentalItem($rentalId, $inventoryNumberId));
            Flash::success(t('rentals.flash.issued_full'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/veranstaltungen/' . $rentalId . '/ausgabe');
    }

    public function unissueItem(Request $request): void
    {
        $issuedItemId = $request->param('id');
        $rentalId = $request->input('rental_id', '') ?? '';

        try {
            $this->commands->dispatch(new UnissueRentalItem($issuedItemId));
            Flash::success(t('rentals.flash.unissued'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/veranstaltungen/' . $rentalId . '/ausgabe');
    }

    /** Bestätigt im Zubehör-Modal die Vollständigkeitsprüfung für einen Posten. */
    public function confirmAccessories(Request $request): void
    {
        $rentalId = $request->param('id');
        $rentalItemId = $request->input('rental_item_id', '') ?? '';

        try {
            $this->commands->dispatch(new ConfirmRentalItemAccessories($rentalItemId));
            Flash::success(t('rentals.flash.accessories_confirmed_full'));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/veranstaltungen/' . $rentalId . '/ausgabe');
    }

    /** Rückgabe-Checkliste: bereits ausgegebenes Material anhand der Inventarnummer als zurückgegeben erfassen. */
    public function returnChecklist(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetRental($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        echo View::page('rentals/return-checklist', [
            'detail' => $detail,
        ], activeNav: 'rentals', title: t('rentals.page_title.return_checklist', ['event' => $detail->rental->eventName]));
    }

    public function returnItem(Request $request): void
    {
        $rentalId = $request->param('id');
        $issuedItemId = $request->input('issued_item_id', '') ?? '';
        // Barcode-/Handscanner-Workflow: statt der Dropdown-Auswahl kann die
        // Inventarnummer direkt als Text gescannt/eingetippt werden.
        $scannedNumber = trim($request->input('number', '') ?? '');
        $functionTestPassed = $request->input('function_test_passed', '1') === '1';

        if ($issuedItemId === '' && $scannedNumber !== '') {
            $normalized = Prefix::normalize($scannedNumber);
            $inventoryNumber = $this->inventoryNumbers->findByNumber($normalized);

            if ($inventoryNumber === null) {
                Flash::error(t('rentals.flash.inventory_number_not_found', ['number' => $scannedNumber]));
                Response::redirect('/veranstaltungen/' . $rentalId . '/rueckgabe');

                return;
            }

            $issuedItem = $this->issuedItems->findActiveByRentalAndInventoryNumber($rentalId, $inventoryNumber->id);
            if ($issuedItem === null) {
                Flash::error(t('rentals.flash.inventory_number_not_open', ['number' => $scannedNumber]));
                Response::redirect('/veranstaltungen/' . $rentalId . '/rueckgabe');

                return;
            }

            $issuedItemId = $issuedItem->id;
        }

        try {
            $this->commands->dispatch(new ReturnRentalItem($issuedItemId, $functionTestPassed));
            Flash::success($functionTestPassed
                ? t('rentals.flash.return_recorded')
                : t('rentals.flash.return_recorded_failed_test'));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/veranstaltungen/' . $rentalId . '/rueckgabe');
    }

    public function undoReturnItem(Request $request): void
    {
        $issuedItemId = $request->param('id');
        $rentalId = $request->input('rental_id', '') ?? '';

        try {
            $this->commands->dispatch(new UndoReturnRentalItem($issuedItemId));
            Flash::success(t('rentals.flash.return_undo_full'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/veranstaltungen/' . $rentalId . '/rueckgabe');
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderForm(?Rental $rental, array $errors, array $old, ?string $editingId = null): void
    {
        $customers = $this->queries->ask(new ListCustomers())->items;
        $venues = $this->venues->findAll();

        // Für die clientseitige Warnung "Stromverteiler mit eigenem RCD
        // nötig" im Veranstaltungsformular (siehe rentals/form.php): Name
        // (klein geschrieben, für den Abgleich mit dem frei eingegebenen
        // Text im Veranstaltungsort-Feld) => needsDistributorWithRcd, als
        // JSON eingebettet, EINE zusätzliche Abfrage statt N+1.
        $venueIds = array_map(static fn ($venue) => $venue->id, $venues);
        $needsDistributorFlagsById = $this->queries->ask(new GetVenuesNeedDistributorFlags($venueIds));
        $needsDistributorFlagsByName = [];
        foreach ($venues as $venue) {
            $needsDistributorFlagsByName[mb_strtolower($venue->name)] = $needsDistributorFlagsById[$venue->id] ?? false;
        }

        echo View::page('rentals/form', [
            'rental' => $rental,
            'customers' => $customers,
            'venues' => $venues,
            'needsDistributorFlagsByName' => $needsDistributorFlagsByName,
            'errors' => $errors,
            'old' => $old,
            'editingId' => $editingId,
        ], activeNav: 'rentals', title: $rental !== null ? t('rentals.edit') : t('rentals.new'));
    }

    /** @return array<string, mixed> */
    private function oldFromCommand(CreateRental|UpdateRental $command): array
    {
        return [
            'customer_id' => $command->customerId,
            'start_date' => $command->startDate,
            'end_date' => $command->endDate,
            'event_name' => $command->eventName,
            'event_location' => $command->eventLocation,
            'deployment_days' => $command->deploymentDays,
        ];
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }

    /** Erzeugt einen für einen Dateinamen sicheren Bestandteil (keine Pfad-/Sonderzeichen). */
    private function sanitizeFilename(string $value): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9_\-]+/', '-', $value) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'Veranstaltung';
    }
}
