<?php

declare(strict_types=1);

namespace App\Application\Sale\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Stock\InsufficientStockException;
use App\Application\Stock\StockFifoAllocator;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\InvoiceItem;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\Sale\Sale;
use App\Domain\Sale\SaleRepositoryInterface;
use App\Domain\Stock\SaleStockConsumption;
use App\Domain\Stock\SaleStockConsumptionRepositoryInterface;
use App\Domain\Stock\SaleStockLotRepositoryInterface;
use App\Shared\Money;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Verkauft eine Menge aus dem reinen Verkaufsbestand (siehe
 * SellArticleFromStock-Klassen-Doc): verbraucht die benötigte Menge FIFO
 * (älteste Charge zuerst, siehe StockFifoAllocator) über
 * App\Domain\Stock\SaleStockLot, legt wie SellInventoryNumberHandler eine
 * eigenständige Entwurfs-Rechnung mit einer Position an und speichert einen
 * Sale-Datensatz MIT $quantity, aber OHNE $inventoryNumberId.
 */
final class SellArticleFromStockHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly CustomerRepositoryInterface $customers,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly SaleRepositoryInterface $sales,
        private readonly SaleStockLotRepositoryInterface $stockLots,
        private readonly SaleStockConsumptionRepositoryInterface $stockConsumptions,
    ) {
    }

    public function handle(CommandInterface $command): array
    {
        assert($command instanceof SellArticleFromStock);

        $article = $this->articles->findById($command->articleId);
        if ($article === null) {
            throw new NotFoundException("Artikel \"{$command->articleId}\" wurde nicht gefunden.");
        }

        $errors = [];

        $quantity = filter_var(trim($command->quantity), FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 1) {
            $errors['quantity'] = 'Bitte eine gültige Menge von mindestens 1 angeben.';
        }

        $customerId = null;
        if ($command->customerId !== null && trim($command->customerId) !== '') {
            $customer = $this->customers->findById($command->customerId);
            if ($customer === null) {
                $errors['customerId'] = 'Der ausgewählte Kunde wurde nicht gefunden.';
            } else {
                $customerId = $customer->id;
            }
        }

        $salePrice = Money::normalize($command->salePrice);
        if ($salePrice === false || $salePrice === null) {
            $errors['salePrice'] = 'Bitte einen gültigen Verkaufspreis angeben.';
        }

        $shippingAmount = null;
        if ($command->shippingAmount !== null && trim($command->shippingAmount) !== '') {
            $shippingAmount = Money::normalize($command->shippingAmount);
            if ($shippingAmount === false) {
                $errors['shippingAmount'] = 'Bitte einen gültigen Versandkostenbetrag angeben (oder leer lassen).';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $lots = $this->stockLots->findAvailableByArticleFifo($article->id);

        try {
            $allocation = StockFifoAllocator::allocate($lots, $quantity);
        } catch (InsufficientStockException $e) {
            throw new ValidationException(['quantity' => $e->getMessage()]);
        }

        $now = new DateTimeImmutable();

        $invoice = new Invoice(
            id: Uuid::v4(),
            customerId: $customerId,
            eventLocation: null,
            venueId: null,
            startDate: $now,
            endDate: $now,
            invoiceNumber: null,
            invoiceDate: null,
            dueDate: null,
            buyerReference: null,
            status: InvoiceStatus::Draft,
            isSmallBusiness: null,
            vatRatePercent: null,
            paidAt: null,
            note: $command->note,
            sourceOfferId: null,
            sourceRentalId: null,
            createdAt: $now,
            updatedAt: $now,
            accessoryFlatRateEnabled: false,
            accessoryFlatRatePercent: null,
            discountType: null,
            discountValue: null,
            shippingAmount: $shippingAmount,
        );
        $this->invoices->save($invoice);

        // Bewusst EINE Position mit quantity=1 und dem vollen Gesamtpreis
        // (nicht quantity=$quantity mit anteiligem Einzelpreis) — eine
        // Division von $salePrice durch $quantity könnte bei Centbeträgen zu
        // Rundungsdifferenzen zwischen Zeilensumme und tatsächlichem
        // Verkaufspreis führen (siehe InvoiceItem::lineTotal() = Einzelpreis
        // * Menge). Die verkaufte Stückzahl steht stattdessen im Freitext.
        $item = new InvoiceItem(
            id: Uuid::v4(),
            invoiceId: $invoice->id,
            articleId: $article->id,
            quantity: 1,
            unitPricePerDay: $salePrice,
            lineNote: "Verkauf aus Verkaufsbestand ({$quantity} Stück)",
        );
        $this->invoices->replaceItems($invoice->id, [$item]);

        $sale = new Sale(
            id: Uuid::v4(),
            inventoryNumberId: null,
            articleId: $article->id,
            quantity: $quantity,
            customerId: $customerId,
            invoiceId: $invoice->id,
            salePrice: $salePrice,
            costOfGoodsSold: $allocation->totalCost,
            shippingAmount: $shippingAmount,
            soldAt: $now,
            note: $command->note,
            createdAt: $now,
        );
        $this->sales->save($sale);

        foreach ($allocation->entries as $entry) {
            $entry->lot->quantityRemaining -= $entry->quantity;
            $this->stockLots->save($entry->lot);

            $this->stockConsumptions->save(new SaleStockConsumption(
                id: Uuid::v4(),
                saleId: $sale->id,
                stockLotId: $entry->lot->id,
                quantity: $entry->quantity,
                unitPurchasePrice: $entry->lot->unitPurchasePrice,
                createdAt: $now,
            ));
        }

        return ['saleId' => $sale->id, 'invoiceId' => $invoice->id];
    }
}
