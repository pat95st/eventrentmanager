<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Article\Query\GetInventoryNumberContext;
use App\Application\Command\CommandBus;
use App\Application\Printing\Command\PrintInventoryNumberLabel;
use App\Application\Printing\Query\ListLabelPrinters;
use App\Application\Query\QueryBus;
use App\Application\Rental\Query\ListRentalHistoryForInventoryNumber;
use App\Application\Sale\Command\SellInventoryNumber;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\ElectricalInspection\ElectricalInspectionRepositoryInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Rma\RmaRepositoryInterface;
use App\Domain\Sale\SaleRepositoryInterface;
use App\Domain\Supplier\SupplierRepositoryInterface;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use App\Infrastructure\Printing\PrinterConnectionException;
use function App\Infrastructure\Http\t;

/** Eigene Detailseite einer einzelnen Inventurnummer (Klick auf die Nummer). */
final class InventoryNumberController
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly CommandBus $commands,
        private readonly SupplierRepositoryInterface $suppliers,
        private readonly RmaRepositoryInterface $rmas,
        private readonly ElectricalInspectionRepositoryInterface $electricalInspections,
        private readonly CustomerRepositoryInterface $customers,
        private readonly SaleRepositoryInterface $sales,
    ) {
    }

    public function show(Request $request): void
    {
        $id = $request->param('id');

        try {
            $context = $this->queries->ask(new GetInventoryNumberContext($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $supplier = $context->inventoryNumber->supplierId !== null
            ? $this->suppliers->findById($context->inventoryNumber->supplierId)
            : null;

        $rentalHistory = $this->queries->ask(new ListRentalHistoryForInventoryNumber($id));
        $rmaCount = $this->rmas->countByInventoryNumber($id);
        $electricalInspectionCount = $this->electricalInspections->countByInventoryNumber($id);
        $latestElectricalInspection = $this->electricalInspections->findLatestByInventoryNumber($id);
        $labelPrinters = $this->queries->ask(new ListLabelPrinters());
        $sale = $this->sales->findByInventoryNumber($id);
        $customers = $sale === null ? $this->customers->findAll() : [];

        echo View::page('inventory-numbers/show', [
            'context' => $context,
            'supplier' => $supplier,
            'rentalHistory' => $rentalHistory,
            'rmaCount' => $rmaCount,
            'electricalInspectionCount' => $electricalInspectionCount,
            'latestElectricalInspection' => $latestElectricalInspection,
            'labelPrinters' => $labelPrinters,
            'sale' => $sale,
            'saleCustomer' => $sale?->customerId !== null ? $this->customers->findById($sale->customerId) : null,
            'customers' => $customers,
            'saleErrors' => [],
        ], activeNav: 'articles', title: 'Inventurnummer: ' . $context->inventoryNumber->number);
    }

    /** Verkauft dieses Exemplar aus dem Bestand (siehe SellInventoryNumberHandler) — legt dabei automatisch eine Rechnung (Entwurf) an. */
    public function sell(Request $request): void
    {
        $id = $request->param('id');
        $customerId = $request->input('customer_id', '') ?? '';
        $salePrice = $request->input('sale_price', '') ?? '';
        $shippingAmount = $request->input('shipping_amount', '') ?? '';
        $note = $request->input('note', '') ?? '';

        try {
            $result = $this->commands->dispatch(new SellInventoryNumber(
                inventoryNumberId: $id,
                customerId: $customerId !== '' ? $customerId : null,
                salePrice: $salePrice,
                shippingAmount: $shippingAmount !== '' ? $shippingAmount : null,
                note: $note !== '' ? $note : null,
            ));

            Flash::success(t('inventory_numbers.sell.flash.success'));
            Response::redirect('/rechnungen/' . $result['invoiceId']);

            return;
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
        } catch (ValidationException $e) {
            Flash::error(t('inventory_numbers.sell.flash.validation_failed'));
        }

        Response::redirect('/inventurnummern/' . $id);
    }

    /** Druckt ein Barcode-Etikett dieser Inventarnummer auf dem gewählten Drucker (siehe PrintInventoryNumberLabelHandler). */
    public function printLabel(Request $request): void
    {
        $id = $request->param('id');
        $printerId = $request->input('printer_id', '') ?? '';

        if ($printerId === '') {
            Flash::error(t('inventory_numbers.label.flash.no_printer_selected'));
            Response::redirect('/inventurnummern/' . $id);

            return;
        }

        try {
            $this->commands->dispatch(new PrintInventoryNumberLabel($id, $printerId));
            Flash::success(t('inventory_numbers.label.flash.printed'));
        } catch (PrinterConnectionException $e) {
            Flash::error($e->getMessage());
        } catch (NotFoundException|ValidationException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/inventurnummern/' . $id);
    }
}
