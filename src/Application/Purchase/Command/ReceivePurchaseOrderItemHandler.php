<?php

declare(strict_types=1);

namespace App\Application\Purchase\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumber;
use App\Domain\InventoryNumber\InventoryNumberGenerator;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Purchase\PurchaseOrderRepositoryInterface;
use App\Domain\Purchase\PurchaseOrderStatus;
use App\Domain\Purchase\PurchaseTargetType;
use App\Domain\Stock\SaleStockLot;
use App\Domain\Stock\SaleStockLotRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Erfasst den Wareneingang einer Bestellposition (ganz oder teilweise):
 * - PurchaseTargetType::RentalPool: legt für jede eingetroffene Einheit eine
 *   einzelne Inventurnummer an (dieselbe Nummernvergabe wie
 *   AddInventoryNumberHandler), Lieferant + der in der Bestellposition
 *   vereinbarte Einkaufspreis werden je Exemplar übernommen.
 * - PurchaseTargetType::SaleStock: legt EINE neue SaleStockLot-Charge mit
 *   der gesamten eingetroffenen Menge an (keine Inventurnummern).
 *
 * Erhöht anschließend PurchaseOrderItem::$receivedQuantity und setzt die
 * Bestellung automatisch auf "Abgeschlossen", sobald ALLE Positionen
 * vollständig eingetroffen sind.
 */
final class ReceivePurchaseOrderItemHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $purchaseOrders,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly SaleStockLotRepositoryInterface $stockLots,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ReceivePurchaseOrderItem);

        $item = $this->purchaseOrders->findItemById($command->purchaseOrderItemId);
        if ($item === null) {
            throw new NotFoundException("Bestellposition \"{$command->purchaseOrderItemId}\" wurde nicht gefunden.");
        }

        $order = $this->purchaseOrders->findById($item->purchaseOrderId);
        if ($order === null) {
            throw new NotFoundException('Zugehörige Bestellung wurde nicht gefunden.');
        }

        if ($order->status !== PurchaseOrderStatus::Ordered) {
            throw new ConflictException('Nur bei einer aufgegebenen (bestellten) Bestellung kann ein Wareneingang erfasst werden.');
        }

        $quantity = filter_var(trim($command->quantity), FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 1) {
            throw new ValidationException(['quantity' => 'Bitte eine gültige Menge von mindestens 1 angeben.']);
        }

        if ($quantity > $item->openQuantity()) {
            throw new ValidationException(['quantity' => "Es sind nur noch {$item->openQuantity()} von {$item->orderedQuantity} Stück offen."]);
        }

        $article = $this->articles->findById($item->articleId);
        if ($article === null) {
            throw new NotFoundException('Zugehöriger Artikel wurde nicht gefunden.');
        }

        $now = new DateTimeImmutable();

        if ($item->targetType === PurchaseTargetType::RentalPool) {
            $this->receiveIntoRentalPool($item, $order->supplierId, $article->id, $quantity, $now);
        } else {
            $this->stockLots->save(new SaleStockLot(
                id: Uuid::v4(),
                articleId: $article->id,
                purchaseOrderItemId: $item->id,
                unitPurchasePrice: $item->unitPrice,
                quantityReceived: $quantity,
                quantityRemaining: $quantity,
                receivedAt: $now,
                createdAt: $now,
            ));
        }

        $item->receivedQuantity += $quantity;
        $this->purchaseOrders->saveItem($item);

        $allItems = $this->purchaseOrders->findItemsByOrder($order->id);
        if (array_reduce($allItems, fn (bool $carry, $i) => $carry && $i->isFullyReceived(), true)) {
            $order->status = PurchaseOrderStatus::Completed;
            $order->updatedAt = $now;
            $this->purchaseOrders->save($order);
        }

        return null;
    }

    private function receiveIntoRentalPool(\App\Domain\Purchase\PurchaseOrderItem $item, string $supplierId, string $articleId, int $quantity, DateTimeImmutable $now): void
    {
        $article = $this->articles->findById($articleId);
        $manufacturer = $article !== null ? $this->manufacturers->findById(($article->manufacturerId ?? '')) : null;

        if ($article === null || $manufacturer === null || $manufacturer->prefix === null || $article->prefix === null) {
            throw new ValidationException([
                'quantity' => 'Für diesen Artikel fehlt noch das Hersteller- und/oder Artikel-Präfix. '
                    . 'Bitte zuerst beim Hersteller und beim Artikel ein Präfix hinterlegen, bevor Wareneingänge in den Vermietpool erfasst werden können.',
            ]);
        }

        $existing = $this->inventoryNumbers->findByArticle($article->id);

        for ($i = 0; $i < $quantity; $i++) {
            $number = InventoryNumberGenerator::nextNumber($existing, $manufacturer->prefix, $article->prefix);
            while ($this->inventoryNumbers->existsByNumber($number)) {
                $existing[] = $this->placeholderInventoryNumber($article->id, $number, $now);
                $number = InventoryNumberGenerator::nextNumber($existing, $manufacturer->prefix, $article->prefix);
            }

            $inventoryNumber = new InventoryNumber(
                id: Uuid::v4(),
                articleId: $article->id,
                number: $number,
                acquisitionDate: $now,
                supplierId: $supplierId,
                receiptId: null,
                purchasePriceOverride: $item->unitPrice,
                storageLocation: null,
                installedFirmwareVersionId: null,
                createdAt: $now,
                updatedAt: $now,
            );
            $this->inventoryNumbers->save($inventoryNumber);
            $existing[] = $inventoryNumber;
        }
    }

    private function placeholderInventoryNumber(string $articleId, string $number, DateTimeImmutable $now): InventoryNumber
    {
        return new InventoryNumber(
            id: 'temp',
            articleId: $articleId,
            number: $number,
            acquisitionDate: null,
            supplierId: null,
            receiptId: null,
            purchasePriceOverride: null,
            storageLocation: null,
            installedFirmwareVersionId: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
