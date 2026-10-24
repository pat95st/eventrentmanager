<?php

declare(strict_types=1);

namespace App\Application\Purchase\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Purchase\PurchaseOrderRepositoryInterface;
use App\Domain\Purchase\PurchaseOrderStatus;
use DateTimeImmutable;

final class MarkPurchaseOrderCancelledHandler implements CommandHandlerInterface
{
    public function __construct(private readonly PurchaseOrderRepositoryInterface $purchaseOrders)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof MarkPurchaseOrderCancelled);

        $order = $this->purchaseOrders->findById($command->id);
        if ($order === null) {
            throw new NotFoundException("Bestellung \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($order->status === PurchaseOrderStatus::Completed || $order->status === PurchaseOrderStatus::Cancelled) {
            throw new ConflictException('Eine abgeschlossene oder bereits stornierte Bestellung kann nicht mehr storniert werden.');
        }

        $order->status = PurchaseOrderStatus::Cancelled;
        $order->updatedAt = new DateTimeImmutable();
        $this->purchaseOrders->save($order);

        return null;
    }
}
