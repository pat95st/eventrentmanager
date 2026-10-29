<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Rental\RentalBundlePlanner;
use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Rental\RentalItemRepositoryInterface;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;
use DateTimeImmutable;

final class UpdateRentalItemHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalItemRepositoryInterface $items,
        private readonly RentalIssuedItemRepositoryInterface $issuedItems,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ?RentalBundlePlanner $bundlePlanner = null,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateRentalItem);

        $item = $this->items->findById($command->id);
        if ($item === null) {
            throw new NotFoundException("Posten \"{$command->id}\" wurde nicht gefunden.");
        }

        $quantity = filter_var($command->quantity, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 1) {
            throw new ValidationException(['quantity' => 'Bitte eine Menge von mindestens 1 angeben.']);
        }

        $alreadyIssued = $this->issuedItems->countByRentalAndArticle($item->rentalId, $item->articleId);
        if ($quantity < $alreadyIssued) {
            throw new ValidationException([
                'quantity' => "Es sind bereits {$alreadyIssued} Exemplare dieses Artikels ausgegeben — die Menge kann nicht darunter gesenkt werden.",
            ]);
        }

        // Bundles (Migration 107): durch gebuchte Bundles belegte Menge darf
        // nicht unterschritten werden (stattdessen das Bundle entfernen).
        $requiredByBundles = $this->bundlePlanner?->requiredQuantity($item->rentalId, $item->articleId) ?? 0;
        if ($quantity < $requiredByBundles) {
            throw new ValidationException([
                'quantity' => "{$requiredByBundles} Exemplare dieses Artikels gehören zu gebuchten Bundles — die Menge kann nicht darunter gesenkt werden. Bitte stattdessen das Bundle entfernen.",
            ]);
        }

        $availableInventoryNumbers = count($this->inventoryNumbers->findActiveByArticle($item->articleId));
        if ($quantity > $availableInventoryNumbers) {
            throw new ValidationException([
                'quantity' => "Für diesen Artikel existieren nur {$availableInventoryNumbers} Inventarnummer(n) — die geplante Menge kann diese Anzahl nicht übersteigen.",
            ]);
        }

        $item->quantity = $quantity;
        $item->updatedAt = new DateTimeImmutable();

        $this->items->save($item);

        return null;
    }
}
