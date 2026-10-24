<?php

declare(strict_types=1);

namespace App\Application\Purchase\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Purchase\PurchaseOrderRepositoryInterface;
use App\Domain\Purchase\PurchaseOrderStatus;
use DateTimeImmutable;

final class MarkPurchaseOrderOrderedHandler implements CommandHandlerInterface
{
    public function __construct(private readonly PurchaseOrderRepositoryInterface $purchaseOrders)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof MarkPurchaseOrderOrdered);

        $order = $this->purchaseOrders->findById($command->id);
        if ($order === null) {
            throw new NotFoundException("Bestellung \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($order->status !== PurchaseOrderStatus::Draft) {
            throw new ConflictException('Nur eine Bestellung im Entwurf kann aufgegeben werden.');
        }

        if ($this->purchaseOrders->findItemsByOrder($order->id) === []) {
            throw new ValidationException(['items' => 'Diese Bestellung enthält keine Positionen und kann daher nicht aufgegeben werden.']);
        }

        $order->status = PurchaseOrderStatus::Ordered;
        $order->orderedAt = new DateTimeImmutable();
        $order->updatedAt = new DateTimeImmutable();
        $this->purchaseOrders->save($order);

        return null;
    }
}
