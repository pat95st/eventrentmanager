<?php

declare(strict_types=1);

namespace App\Application\Purchase\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Purchase\PurchaseOrder;
use App\Domain\Purchase\PurchaseOrderItem;
use App\Domain\Purchase\PurchaseOrderRepositoryInterface;
use App\Domain\Purchase\PurchaseOrderStatus;
use App\Domain\Supplier\SupplierRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreatePurchaseOrderHandler implements CommandHandlerInterface
{
    use ValidatesPurchaseOrderInput;

    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $purchaseOrders,
        private readonly SupplierRepositoryInterface $suppliers,
        private readonly ArticleRepositoryInterface $articles,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreatePurchaseOrder);

        [$errors, $data] = $this->validatePurchaseOrderInput($this->suppliers, $this->articles, $command->supplierId, $command->note, $command->items);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $order = new PurchaseOrder(
            id: Uuid::v4(),
            supplierId: $data['supplierId'],
            status: PurchaseOrderStatus::Draft,
            orderedAt: null,
            note: $data['note'],
            createdAt: $now,
            updatedAt: $now,
        );
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

        return $order->id;
    }
}
