<?php

declare(strict_types=1);

namespace App\Application\Rental;

use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\ArticleSet\ArticleSet;
use App\Domain\ArticleSet\ArticleSetRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Rental\RentalItem;
use App\Domain\Rental\RentalItemRepositoryInterface;
use App\Domain\Rental\RentalSetBooking;
use App\Domain\Rental\RentalSetBookingRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Plant Sets inkl. gewählter Upgrades in Veranstaltungen ein (Migration 113).
 * Enthaltene Bundles werden über RentalBundlePlanner::checkAdd() in ihre
 * Bestandteile aufgelöst (inkl. automatischer Wahl der Alternativen), aber
 * NICHT als eigenes RentalBundle gebucht — abgerechnet wird das Set zum
 * Festpreis. Die eingeplanten Artikel stehen als RentalItem und zusätzlich
 * in RentalSetBooking::$allocations (Schutz vor Einzel-Entfernung,
 * Abrechnung in CreateInvoiceFromRentalHandler).
 */
final class RentalSetPlanner
{
    public function __construct(
        private readonly ArticleSetRepositoryInterface $sets,
        private readonly ArticleRepositoryInterface $articles,
        private readonly RentalItemRepositoryInterface $rentalItems,
        private readonly RentalSetBookingRepositoryInterface $bookings,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly RentalBundlePlanner $bundlePlanner,
        private readonly \App\Domain\Rental\RentalIssuedItemRepositoryInterface $issuedItems,
    ) {
    }

    /**
     * Bedarf je physischem Artikel für $quantity Sets samt Upgrades, ohne zu speichern.
     *
     * @param string[] $upgradeIds
     * @param array<string, int> $alsoPlanned bereits anderweitig eingeplante Mengen (z. B. dieselbe Umwandlung)
     * @return array<string, int>
     */
    public function checkAdd(string $rentalId, ArticleSet $set, int $quantity, array $upgradeIds, array $alsoPlanned = [], ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): array
    {
        $lines = [];
        foreach ($this->sets->findItems($set->id) as $item) {
            $lines[] = [$item->articleId, $item->quantity * $quantity];
        }
        foreach ($upgradeIds as $upgradeId) {
            $upgrade = $this->sets->findUpgradeById($upgradeId);
            if ($upgrade === null || $upgrade->setId !== $set->id) {
                continue;
            }
            foreach ($upgrade->items as $item) {
                $lines[] = [$item->articleId, $item->quantity * $quantity];
            }
        }
        if ($lines === []) {
            throw new ValidationException(['items' => "Das Set „{$set->name}“ enthält keine Artikel."]);
        }

        $demand = [];
        foreach ($lines as [$articleId, $lineQuantity]) {
            $article = $this->articles->findById($articleId);
            if ($article === null) {
                continue;
            }
            if ($article->isBundle) {
                $planned = $alsoPlanned;
                foreach ($demand as $id => $q) {
                    $planned[$id] = ($planned[$id] ?? 0) + $q;
                }
                foreach ($this->bundlePlanner->checkAdd($rentalId, $article, $lineQuantity, $planned, $from, $to) as $componentId => $componentDemand) {
                    $demand[$componentId] = ($demand[$componentId] ?? 0) + $componentDemand;
                }
                continue;
            }
            $demand[$articleId] = ($demand[$articleId] ?? 0) + $lineQuantity;
        }

        foreach ($demand as $articleId => $additional) {
            $existing = $rentalId !== '' ? ($this->rentalItems->findByRentalAndArticle($rentalId, $articleId)?->quantity ?? 0) : 0;
            $available = count($this->inventoryNumbers->findActiveByArticle($articleId));
            if ($existing + ($alsoPlanned[$articleId] ?? 0) + $additional > $available) {
                throw new ValidationException([
                    'quantity' => "Für einen Artikel des Sets „{$set->name}“ existieren nur {$available} Inventarnummer(n) — die geplante Menge übersteigt diese Anzahl.",
                ]);
            }
        }

        return $demand;
    }

    /** @param string[] $upgradeIds */
    public function add(string $rentalId, ArticleSet $set, int $quantity, array $upgradeIds): string
    {
        $demand = $this->checkAdd($rentalId, $set, $quantity, $upgradeIds);
        $now = new DateTimeImmutable();

        foreach ($demand as $articleId => $additional) {
            $item = $this->rentalItems->findByRentalAndArticle($rentalId, $articleId);
            if ($item !== null) {
                $item->quantity += $additional;
                $item->updatedAt = $now;
            } else {
                $item = new RentalItem(
                    id: Uuid::v4(),
                    rentalId: $rentalId,
                    articleId: $articleId,
                    quantity: $additional,
                    accessoryChecked: false,
                    createdAt: $now,
                    updatedAt: $now,
                );
            }
            $this->rentalItems->save($item);
        }

        $booking = new RentalSetBooking(Uuid::v4(), $rentalId, $set->id, $quantity, array_values($upgradeIds), $demand, $now);
        $this->bookings->save($booking);

        return $booking->id;
    }

    /** Entfernt ein gebuchtes Set samt der dafür eingeplanten Mengen. */
    public function remove(RentalSetBooking $booking): void
    {
        $now = new DateTimeImmutable();
        $plan = [];
        foreach ($booking->allocations as $articleId => $allocated) {
            $item = $this->rentalItems->findByRentalAndArticle($booking->rentalId, (string) $articleId);
            if ($item === null) {
                continue;
            }
            $newQuantity = $item->quantity - $allocated;
            if ($newQuantity < $this->issuedItems->countByRentalAndArticle($booking->rentalId, (string) $articleId)) {
                throw new ValidationException(['set' => 'Von den Artikeln dieses Sets sind bereits Exemplare ausgegeben — bitte zuerst die Ausgabe rückgängig machen.']);
            }
            $plan[] = [$item, $newQuantity];
        }
        foreach ($plan as [$item, $newQuantity]) {
            if ($newQuantity <= 0) {
                $this->rentalItems->delete($item->id);
            } else {
                $item->quantity = $newQuantity;
                $item->updatedAt = $now;
                $this->rentalItems->save($item);
            }
        }
        $this->bookings->delete($booking->id);
    }
}
