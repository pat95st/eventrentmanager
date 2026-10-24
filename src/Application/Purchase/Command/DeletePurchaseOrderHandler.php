<?php

declare(strict_types=1);

namespace App\Application\Purchase\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Purchase\PurchaseOrderRepositoryInterface;

final class DeletePurchaseOrderHandler implements CommandHandlerInterface
{
    public function __construct(private readonly PurchaseOrderRepositoryInterface $purchaseOrders)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeletePurchaseOrder);

        $order = $this->purchaseOrders->findById($command->id);
        if ($order === null) {
            throw new NotFoundException("Bestellung \"{$command->id}\" wurde nicht gefunden.");
        }

        if (!$order->isEditable()) {
            throw new ConflictException('Diese Bestellung wurde bereits aufgegeben und kann nicht mehr gelöscht werden.');
        }

        $this->purchaseOrders->delete($order->id);

        return null;
    }
}
