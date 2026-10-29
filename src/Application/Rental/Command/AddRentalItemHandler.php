<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Rental\RentalBundlePlanner;
use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Rental\RentalItem;
use App\Domain\Rental\RentalItemRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Fügt einer Vermietung einen Artikel (auf Artikel-Ebene, mit Menge) hinzu.
 * Ist der Artikel bereits geplant, wird die Menge auf die bestehende Zeile
 * addiert statt eine zweite Zeile für denselben Artikel anzulegen (analog zu
 * AddCaseContentItemHandler). Die resultierende Gesamtmenge darf die Anzahl
 * der für diesen Artikel angelegten Inventurnummern nicht übersteigen.
 */
final class AddRentalItemHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalItemRepositoryInterface $items,
        private readonly RentalRepositoryInterface $rentals,
        private readonly ArticleRepositoryInterface $articles,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ?RentalBundlePlanner $bundlePlanner = null,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddRentalItem);

        if ($this->rentals->findById($command->rentalId) === null) {
            throw new NotFoundException("Vermietung \"{$command->rentalId}\" wurde nicht gefunden.");
        }

        $errors = [];

        $article = $this->articles->findById($command->articleId);
        if ($article === null) {
            $errors['articleId'] = 'Bitte einen gültigen Artikel auswählen.';
        }

        $quantity = filter_var($command->quantity, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 1) {
            $errors['quantity'] = 'Bitte eine Menge von mindestens 1 angeben.';
        }

        // Bundles (Migration 107): nicht einzeln vermietbare Artikel nur über
        // ihr Bundle; ein Bundle wird in seine Bestandteile aufgelöst.
        if ($article !== null && !$article->isBundle && !$article->isRentableIndividually) {
            $errors['articleId'] = "„{$article->name}“ ist nicht einzeln vermietbar — bitte das passende Bundle wählen.";
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        if ($article->isBundle) {
            if ($this->bundlePlanner === null) {
                throw new ValidationException(['articleId' => 'Bundles können hier nicht eingeplant werden.']);
            }

            return $this->bundlePlanner->add($command->rentalId, $article, (int) $quantity);
        }

        $existing = $this->items->findByRentalAndArticle($command->rentalId, $command->articleId);
        $prospectiveQuantity = ($existing?->quantity ?? 0) + $quantity;
        $availableInventoryNumbers = count($this->inventoryNumbers->findActiveByArticle($command->articleId));

        if ($prospectiveQuantity > $availableInventoryNumbers) {
            throw new ValidationException([
                'quantity' => "Für diesen Artikel existieren nur {$availableInventoryNumbers} Inventarnummer(n) — die geplante Menge kann diese Anzahl nicht übersteigen.",
            ]);
        }

        $now = new DateTimeImmutable();

        if ($existing !== null) {
            $existing->quantity += $quantity;
            $existing->updatedAt = $now;
            $this->items->save($existing);

            return $existing->id;
        }

        $item = new RentalItem(
            id: Uuid::v4(),
            rentalId: $command->rentalId,
            articleId: $command->articleId,
            quantity: $quantity,
            accessoryChecked: false,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->items->save($item);

        return $item->id;
    }
}
