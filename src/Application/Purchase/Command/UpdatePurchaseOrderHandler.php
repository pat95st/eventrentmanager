<?php

declare(strict_types=1);

namespace App\Application\Purchase\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Purchase\PurchaseOrderItem;
use App\Domain\Purchase\PurchaseOrderRepositoryInterface;
use App\Domain\Supplier\SupplierRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Nur erlaubt, solange die Bestellung noch im Entwurf ist (siehe
 * PurchaseOrder::isEditable()) — danach gilt die Positionsliste als
 * verbindlich aufgegeben, ein Ersetzen würde sonst bereits erfasste
 * Wareneingänge (receivedQuantity) verwaisen lassen.
 */
final class UpdatePurchaseOrderHandler implements CommandHandlerInterface
{
    use ValidatesPurchaseOrderInput;

    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $purchaseOrders,
        private readonly SupplierRepositoryInterface $suppliers,
        private readonly ArticleRepositoryInterface $articles,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdatePurchaseOrder);

        $order = $this->purchaseOrders->findById($command->id);
        if ($order === null) {
            throw new NotFoundException("Bestellung \"{$command->id}\" wurde nicht gefunden.");
        }

        if (!$order->isEditable()) {
            throw new ConflictException('Diese Bestellung wurde bereits aufgegeben und kann nicht mehr bearbeitet werden.');
        }

        [$errors, $data] = $this->validatePurchaseOrderInput($this->suppliers, $this->articles, $command->supplierId, $command->note, $command->items);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $order->supplierId = $data['supplierId'];
        $order->note = $data['note'];
        $order->updatedAt = new DateTimeImmutable();
        $this->purchaseOrders->save($order);

        $items = array_map(
            fn (array $item) => new PurchaseOrderItem(
                id: Uuid::v4(),
                purchaseOrderId: $order->id,
                articleId: $item['articleId'],
                targetType: $item['targetType'],
                orderedQuantity: $item['orderedQuantity'],
                unitPrice: $item['unitPrice'],
            ),
            $data['items']
        );
        $this->purchaseOrders->replaceItems($order->id, $items);

        return null;
    }
}
