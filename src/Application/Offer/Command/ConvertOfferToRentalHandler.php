<?php

declare(strict_types=1);

namespace App\Application\Offer\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Rental\RentalBundlePlanner;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Offer\OfferRepositoryInterface;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalItem;
use App\Domain\Rental\RentalItemRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Wandelt ein Angebot in eine Veranstaltung (Rental) um: legt eine neue
 * Rental mit Kunde/Veranstaltungsort/Zeitraum des Angebots an und überträgt
 * dessen Positionen (Artikel + Menge; der Angebotspreis geht dabei NICHT in
 * die Veranstaltung über, da Rental/RentalItem kein Preisfeld kennt).
 *
 * Laut Aufgabenstellung ist die Umwandlung nicht auf den Status "Angenommen"
 * beschränkt (nur dort besonders hervorgehoben, siehe offers/show.php),
 * lediglich ein bereits umgewandeltes Angebot kann nicht ein zweites Mal
 * umgewandelt werden (siehe convertedRentalId).
 *
 * Das ursprüngliche Angebot bleibt unverändert bestehen (kein Löschen),
 * lediglich convertedRentalId wird gesetzt, damit die Angebots-Detailseite
 * einen Link auf die entstandene Veranstaltung anzeigen kann.
 */
final class ConvertOfferToRentalHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly OfferRepositoryInterface $offers,
        private readonly RentalRepositoryInterface $rentals,
        private readonly RentalItemRepositoryInterface $rentalItems,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        // Bundles (Migration 107): Bundle-Positionen werden in ihre
        // Bestandteile aufgelöst.
        private readonly ?ArticleRepositoryInterface $articles = null,
        private readonly ?RentalBundlePlanner $bundlePlanner = null,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof ConvertOfferToRental);

        $offer = $this->offers->findById($command->offerId);
        if ($offer === null) {
            throw new NotFoundException("Angebot \"{$command->offerId}\" wurde nicht gefunden.");
        }

        if ($offer->convertedRentalId !== null) {
            throw new ConflictException('Dieses Angebot wurde bereits in eine Veranstaltung umgewandelt.');
        }

        if ($offer->customerId === null) {
            throw new ValidationException(['customerId' => 'Für die Umwandlung muss dem Angebot zuerst ein Kunde zugeordnet werden.']);
        }

        // Eine Veranstaltung (Rental) braucht zwingend einen Zeitraum — anders
        // als das Angebot selbst, das seit Migration 102 auch ohne Zeitraum
        // bestehen kann (siehe Offer::$startDate/$endDate).
        if ($offer->startDate === null || $offer->endDate === null) {
            throw new ValidationException(['startDate' => 'Für die Umwandlung muss dem Angebot zuerst ein Zeitraum (Start-/Enddatum) zugeordnet werden.']);
        }

        $items = $this->offers->findItemsByOffer($offer->id);

        // Einzelpositionen (je Artikel zusammengefasst) und Bundle-Positionen
        // trennen; Bundles werden über RentalBundlePlanner in ihre
        // Bestandteile aufgelöst (Migration 107).
        $individualQuantities = [];
        $bundleItems = [];
        foreach ($items as $item) {
            $article = $this->articles?->findById($item->articleId);
            if ($article !== null && $article->isBundle && $this->bundlePlanner !== null) {
                $bundleItems[] = [$article, $item->quantity];
                continue;
            }
            $individualQuantities[$item->articleId] = ($individualQuantities[$item->articleId] ?? 0) + $item->quantity;
        }

        foreach ($individualQuantities as $articleId => $quantity) {
            $availableInventoryNumbers = count($this->inventoryNumbers->findActiveByArticle($articleId));
            if ($quantity > $availableInventoryNumbers) {
                throw new ValidationException([
                    'items' => "Für einen Artikel existieren nur {$availableInventoryNumbers} Inventarnummer(n) — die Menge im Angebot übersteigt diese Anzahl.",
                ]);
            }
        }

        // Bestandteil-Bedarf aller Bundles zusammen mit den Einzelpositionen
        // prüfen, bevor irgendetwas gespeichert wird.
        $plannedSoFar = $individualQuantities;
        foreach ($bundleItems as [$bundleArticle, $bundleQuantity]) {
            foreach ($this->bundlePlanner->checkAdd('', $bundleArticle, $bundleQuantity, $plannedSoFar) as $componentId => $demand) {
                $plannedSoFar[$componentId] = ($plannedSoFar[$componentId] ?? 0) + $demand;
            }
        }

        $now = new DateTimeImmutable();
        $eventName = $offer->eventLocation !== null && trim($offer->eventLocation) !== ''
            ? $offer->eventLocation
            : 'Veranstaltung aus Angebot vom ' . $offer->createdAt->format('d.m.Y');

        $rental = new Rental(
            id: Uuid::v4(),
            customerId: $offer->customerId,
            startDate: $offer->startDate,
            endDate: $offer->endDate,
            eventName: $eventName,
            eventLocation: $offer->eventLocation,
            venueId: $offer->venueId,
            deploymentDays: $offer->maxDeploymentDays(),
            createdAt: $now,
            updatedAt: $now,
        );
        $this->rentals->save($rental);

        foreach ($individualQuantities as $articleId => $quantity) {
            $this->rentalItems->save(new RentalItem(
                id: Uuid::v4(),
                rentalId: $rental->id,
                articleId: $articleId,
                quantity: $quantity,
                accessoryChecked: false,
                createdAt: $now,
                updatedAt: $now,
            ));
        }
        foreach ($bundleItems as [$bundleArticle, $bundleQuantity]) {
            $this->bundlePlanner->add($rental->id, $bundleArticle, $bundleQuantity);
        }

        $offer->convertedRentalId = $rental->id;
        $offer->updatedAt = $now;
        $this->offers->save($offer);

        return $rental->id;
    }
}
