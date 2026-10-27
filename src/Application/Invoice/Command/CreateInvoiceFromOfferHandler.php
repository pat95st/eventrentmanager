<?php

declare(strict_types=1);

namespace App\Application\Invoice\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Stock\InsufficientStockException;
use App\Application\Stock\StockFifoAllocator;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\InvoiceItem;
use App\Domain\Invoice\InvoiceLaborItem;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Offer\OfferRepositoryInterface;
use App\Domain\Sale\Sale;
use App\Domain\Sale\SaleRepositoryInterface;
use App\Domain\Stock\SaleStockConsumption;
use App\Domain\Stock\SaleStockConsumptionRepositoryInterface;
use App\Domain\Stock\SaleStockLotRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Erstellt eine neue Entwurfs-Rechnung aus einem bestehenden Angebot: Kunde,
 * Veranstaltungsort, Zeitraum UND die Positionen inklusive ihrer bereits im
 * Angebot festgelegten Preise werden 1:1 übernommen (anders als bei
 * CreateInvoiceFromRentalHandler, wo Rental/RentalItem keine Preise kennen
 * und ein Preis erst vorgeschlagen werden muss). source_offer_id wird
 * gesetzt, damit die Rechnung ihre Herkunft dokumentiert.
 *
 * Wie ConvertOfferToRentalHandler ist die Umwandlung bewusst NICHT auf einen
 * bestimmten Angebotsstatus beschränkt (dieselbe Begründung: ein Angebot
 * kann aus verschiedenen fachlichen Gründen ohnehin schon "Angenommen"
 * markiert worden sein, oder die Rechnung wird direkt aus einem noch
 * offenen Angebot vorbereitet) — ein Angebot kann außerdem beliebig oft in
 * Rechnungen umgewandelt werden (z. B. Teil-/Schlussrechnung), anders als
 * die Umwandlung in eine Veranstaltung, die nur einmal möglich ist.
 *
 * Verlangt mindestens eine Position im Angebot (ValidationException sonst):
 * eine Rechnung OHNE jede Position anzulegen wäre zwar technisch möglich
 * (auch ein eigenständiger Entwurf kann ohne Positionen gespeichert werden),
 * aber bei einer Umwandlung AUS einem Angebot ist ein leeres Angebot so gut
 * wie sicher ein Bedienfehler — hier wird bewusst strenger geprüft als beim
 * eigenständigen Anlegen (bei der Prüfung zählen auch Verkaufspositionen,
 * siehe unten).
 *
 * Verkaufspositionen (OfferSaleItem, siehe dortige Klassen-Doc): jede noch
 * NICHT abgerechnete Position (fulfilledSaleId === null) wird hier
 * zusätzlich zu den gewöhnlichen InvoiceItem-Zeilen abgerechnet — legt genau
 * wie SellInventoryNumberHandler/SellArticleFromStockHandler einen Sale-
 * Datensatz an (Vermietpool-Exemplar ODER FIFO-Verbrauch aus dem
 * Verkaufsbestand) und markiert die Position danach als abgerechnet
 * (OfferRepositoryInterface::markSaleItemFulfilled()), damit ein Angebot
 * beliebig oft erneut in Rechnungen umgewandelt werden kann (z. B. Teil-/
 * Schlussrechnung, siehe Klassen-Doc oben), ohne denselben Verkauf ein
 * zweites Mal auszulösen — bereits abgerechnete Positionen werden bei einem
 * erneuten Aufruf einfach übersprungen.
 */
final class CreateInvoiceFromOfferHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly OfferRepositoryInterface $offers,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly SaleRepositoryInterface $sales,
        private readonly SaleStockLotRepositoryInterface $stockLots,
        private readonly SaleStockConsumptionRepositoryInterface $stockConsumptions,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ArticleRepositoryInterface $articles,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateInvoiceFromOffer);

        $offer = $this->offers->findById($command->offerId);
        if ($offer === null) {
            throw new NotFoundException("Angebot \"{$command->offerId}\" wurde nicht gefunden.");
        }

        $offerItems = $this->offers->findItemsByOffer($offer->id);
        $allSaleItems = $this->offers->findSaleItemsByOffer($offer->id);
        $openSaleItems = array_values(array_filter($allSaleItems, fn ($item) => !$item->isFulfilled()));

        if ($offerItems === [] && $openSaleItems === []) {
            throw new ValidationException(['items' => 'Dieses Angebot enthält keine (noch offenen) Positionen und kann daher nicht in eine Rechnung umgewandelt werden.']);
        }

        $now = new DateTimeImmutable();
        $invoice = new Invoice(
            id: Uuid::v4(),
            customerId: $offer->customerId,
            eventLocation: $offer->eventLocation,
            venueId: $offer->venueId,
            startDate: $offer->startDate,
            endDate: $offer->endDate,
            invoiceNumber: null,
            invoiceDate: null,
            dueDate: null,
            buyerReference: null,
            status: InvoiceStatus::Draft,
            isSmallBusiness: null,
            vatRatePercent: null,
            paidAt: null,
            note: $offer->note,
            sourceOfferId: $offer->id,
            sourceRentalId: null,
            createdAt: $now,
            updatedAt: $now,
            // Zubehörpauschale/Kombirabatt werden — wie die Positionspreise —
            // 1:1 vom Angebot übernommen, statt frisch aus den aktuellen
            // PricingSettings gesnapshotet zu werden (identische Begründung
            // wie beim Preis: das Angebot hat diese Werte bereits verbindlich
            // festgelegt).
            accessoryFlatRateEnabled: $offer->accessoryFlatRateEnabled,
            accessoryFlatRatePercent: $offer->accessoryFlatRatePercent,
            discountType: $offer->discountType,
            discountValue: $offer->discountValue,
        );
        $this->invoices->save($invoice);

        $items = array_map(
            fn ($item) => new InvoiceItem(
                id: Uuid::v4(),
                invoiceId: $invoice->id,
                articleId: $item->articleId,
                quantity: $item->quantity,
                unitPricePerDay: $item->unitPricePerDay,
                lineNote: $item->lineNote,
            ),
            $offerItems
        );

        // Verkaufspositionen werden VOR dem einzigen replaceItems()-Aufruf in
        // dieselbe Positionsliste eingehängt (jeweils quantity=1 mit dem
        // vollen Zeilenpreis als unitPricePerDay — identisches Muster wie
        // SellInventoryNumberHandler/SellArticleFromStockHandler, siehe dort
        // für die Rundungsbegründung), damit sie auf der Rechnung normal als
        // Position erscheinen und in deren Summen einfließen. replaceItems()
        // ersetzt IMMER die gesamte Positionsliste, ein zweiter Aufruf würde
        // die hier eingefügten Mietpositionen sonst wieder löschen.
        foreach ($openSaleItems as $saleItem) {
            $sale = $this->fulfillSaleItem($saleItem, $invoice->id, $offer->customerId, $now);
            $items[] = new InvoiceItem(
                id: Uuid::v4(),
                invoiceId: $invoice->id,
                articleId: $saleItem->articleId,
                quantity: 1,
                unitPricePerDay: $sale->salePrice,
                lineNote: $saleItem->lineNote ?? ($saleItem->inventoryNumberId === null ? "Verkauf aus Verkaufsbestand ({$saleItem->quantity} Stück)" : 'Verkauf aus Angebot'),
            );
        }

        $this->invoices->replaceItems($invoice->id, $items);

        $offerLaborItems = $this->offers->findLaborItemsByOffer($offer->id);
        $invoiceLaborItems = array_map(
            fn ($item) => new InvoiceLaborItem(
                id: Uuid::v4(),
                invoiceId: $invoice->id,
                laborTimeArticleId: $item->laborTimeArticleId,
                userId: $item->userId,
                hours: $item->hours,
                unitRatePerHour: $item->unitRatePerHour,
                billingUnit: $item->billingUnit,
            ),
            $offerLaborItems
        );
        $this->invoices->replaceLaborItems($invoice->id, $invoiceLaborItems);

        return $invoice->id;
    }

    private function fulfillSaleItem(\App\Domain\Offer\OfferSaleItem $saleItem, string $invoiceId, ?string $customerId, DateTimeImmutable $now): Sale
    {
        if ($saleItem->inventoryNumberId !== null) {
            if ($this->sales->findByInventoryNumber($saleItem->inventoryNumberId) !== null) {
                throw new ValidationException(['saleItems' => 'Ein Exemplar aus diesem Angebot wurde inzwischen bereits anderweitig verkauft.']);
            }

            $inventoryNumber = $this->inventoryNumbers->findById($saleItem->inventoryNumberId);
            $article = $inventoryNumber !== null ? $this->articles->findById($inventoryNumber->articleId) : null;
            $costOfGoodsSold = $inventoryNumber !== null ? $inventoryNumber->effectivePurchasePrice($article?->purchasePrice) : null;

            $sale = new Sale(
                id: Uuid::v4(),
                inventoryNumberId: $saleItem->inventoryNumberId,
                articleId: $saleItem->articleId,
                quantity: 1,
                customerId: $customerId,
                invoiceId: $invoiceId,
                salePrice: (string) $saleItem->unitPrice,
                costOfGoodsSold: $costOfGoodsSold,
                shippingAmount: null,
                soldAt: $now,
                note: $saleItem->lineNote,
                createdAt: $now,
            );
            $this->sales->save($sale);
        } else {
            $lots = $this->stockLots->findAvailableByArticleFifo($saleItem->articleId);

            try {
                $allocation = StockFifoAllocator::allocate($lots, $saleItem->quantity);
            } catch (InsufficientStockException $e) {
                throw new ValidationException(['saleItems' => $e->getMessage()]);
            }

            $sale = new Sale(
                id: Uuid::v4(),
                inventoryNumberId: null,
                articleId: $saleItem->articleId,
                quantity: $saleItem->quantity,
                customerId: $customerId,
                invoiceId: $invoiceId,
                salePrice: number_format($saleItem->lineTotal(), 2, '.', ''),
                costOfGoodsSold: $allocation->totalCost,
                shippingAmount: null,
                soldAt: $now,
                note: $saleItem->lineNote,
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
        }

        $this->offers->markSaleItemFulfilled($saleItem->id, $sale->id);

        return $sale;
    }
}
