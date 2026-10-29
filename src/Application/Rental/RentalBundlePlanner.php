<?php

declare(strict_types=1);

namespace App\Application\Rental;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleBundleComponentRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Rental\RentalBundle;
use App\Domain\Rental\RentalBundleRepositoryInterface;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;
use App\Domain\Rental\RentalItem;
use App\Domain\Rental\RentalItemRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Plant Bundles in Vermietungen ein (Migration 107): ein gebuchtes Bundle
 * wird in seine physischen Bestandteile aufgelöst, die wie bisher als
 * RentalItem (eine Zeile je Artikel, Menge zusammengefasst) geplant und
 * ausgegeben werden. RentalBundle merkt sich die gebuchten Bundles, damit
 * deren Bestandteile nicht einzeln unter die Bundle-Menge gesenkt oder
 * entfernt werden können und die Rechnung zum Bundle-Preis abrechnet.
 *
 * Alternativen (Migration 111): Je Bestandteil wird der Hauptartikel
 * eingeplant. Reicht dessen freie Menge im Zeitraum nicht (aktive
 * Inventarnummern abzüglich der Planung überschneidender Veranstaltungen
 * und dieser Veranstaltung), wird die erste Alternative mit genügend freier
 * Menge genommen. Passt keine, bleibt es beim Hauptartikel (und dessen
 * Bestandsprüfung entscheidet wie bisher). Die tatsächlich belegten Artikel
 * stehen in RentalBundle::$allocations.
 *
 * Gemeinsam genutzt von AddRentalItemHandler, ConvertOfferToRentalHandler,
 * RemoveRentalBundleHandler sowie Update-/DeleteRentalItemHandler.
 */
final class RentalBundlePlanner
{
    public function __construct(
        private readonly RentalItemRepositoryInterface $rentalItems,
        private readonly RentalBundleRepositoryInterface $rentalBundles,
        private readonly ArticleBundleComponentRepositoryInterface $bundleComponents,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly RentalIssuedItemRepositoryInterface $issuedItems,
        private readonly ?RentalRepositoryInterface $rentals = null,
    ) {
    }

    /** Menge eines Artikels, die in dieser Vermietung durch gebuchte Bundles belegt ist. */
    public function requiredQuantity(string $rentalId, string $articleId): int
    {
        $required = 0;
        foreach ($this->rentalBundles->findByRental($rentalId) as $rentalBundle) {
            $required += $this->allocationsOf($rentalBundle)[$articleId] ?? 0;
        }

        return $required;
    }

    /**
     * Wählt je Bestandteil den einzuplanenden Artikel (Hauptartikel oder
     * Alternative) und prüft den Bestand, ohne etwas zu speichern.
     *
     * @param array<string, int> $alsoPlanned zusätzlich bereits eingeplante Mengen je Artikel (z. B. andere Positionen derselben Umwandlung)
     * @param DateTimeImmutable|null $from Zeitraum für die Verfügbarkeit; ohne Angabe der der Vermietung $rentalId
     * @return array<string, int> zusätzlicher Bedarf je eingeplantem Artikel
     */
    public function checkAdd(string $rentalId, Article $bundle, int $quantity, array $alsoPlanned = [], ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): array
    {
        $components = $this->bundleComponents->findByBundle($bundle->id);
        if ($components === []) {
            throw new ValidationException(['articleId' => "Das Bundle „{$bundle->name}“ hat keine Bestandteile und kann daher nicht vermietet werden."]);
        }

        if (($from === null || $to === null) && $rentalId !== '' && $this->rentals !== null) {
            $rental = $this->rentals->findById($rentalId);
            $from ??= $rental?->startDate;
            $to ??= $rental?->endDate;
        }
        $usedElsewhere = $this->usageInOverlappingRentals($rentalId, $from, $to);

        $demand = [];
        foreach ($components as $component) {
            $need = $component->quantity * $quantity;
            $chosen = $component->componentArticleId;
            foreach (array_merge([$component->componentArticleId], $component->alternativeArticleIds) as $candidate) {
                $free = count($this->inventoryNumbers->findActiveByArticle($candidate))
                    - ($usedElsewhere[$candidate] ?? 0)
                    - $this->plannedInRental($rentalId, $candidate)
                    - ($alsoPlanned[$candidate] ?? 0)
                    - ($demand[$candidate] ?? 0);
                if ($free >= $need) {
                    $chosen = $candidate;
                    break;
                }
            }
            $demand[$chosen] = ($demand[$chosen] ?? 0) + $need;
        }

        foreach ($demand as $articleId => $additional) {
            $available = count($this->inventoryNumbers->findActiveByArticle($articleId));
            if ($this->plannedInRental($rentalId, $articleId) + ($alsoPlanned[$articleId] ?? 0) + $additional > $available) {
                throw new ValidationException([
                    'quantity' => "Für einen Bestandteil des Bundles „{$bundle->name}“ existieren nur {$available} Inventarnummer(n) — die geplante Menge übersteigt diese Anzahl.",
                ]);
            }
        }

        return $demand;
    }

    /** Bucht $quantity Bundles und plant deren Bestandteile ein. Liefert die RentalBundle-ID. */
    public function add(string $rentalId, Article $bundle, int $quantity): string
    {
        $demand = $this->checkAdd($rentalId, $bundle, $quantity);
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

        $rentalBundle = $this->rentalBundles->findByRentalAndBundle($rentalId, $bundle->id);
        if ($rentalBundle !== null) {
            $allocations = $this->allocationsOf($rentalBundle);
            $rentalBundle->quantity += $quantity;
            $rentalBundle->updatedAt = $now;
        } else {
            $allocations = [];
            $rentalBundle = new RentalBundle(
                id: Uuid::v4(),
                rentalId: $rentalId,
                bundleArticleId: $bundle->id,
                quantity: $quantity,
                createdAt: $now,
                updatedAt: $now,
            );
        }
        foreach ($demand as $articleId => $additional) {
            $allocations[$articleId] = ($allocations[$articleId] ?? 0) + $additional;
        }
        $rentalBundle->allocations = $allocations;
        $this->rentalBundles->save($rentalBundle);

        return $rentalBundle->id;
    }

    /**
     * Entfernt ein gebuchtes Bundle samt seiner Bestandteil-Mengen. Bereits
     * ausgegebene Exemplare müssen vorher zurückgenommen werden.
     */
    public function remove(RentalBundle $rentalBundle): void
    {
        $now = new DateTimeImmutable();
        $plan = [];
        foreach ($this->allocationsOf($rentalBundle) as $articleId => $allocated) {
            $item = $this->rentalItems->findByRentalAndArticle($rentalBundle->rentalId, (string) $articleId);
            if ($item === null) {
                continue;
            }
            $newQuantity = $item->quantity - $allocated;
            $issued = $this->issuedItems->countByRentalAndArticle($rentalBundle->rentalId, (string) $articleId);
            if ($newQuantity < $issued) {
                throw new ValidationException([
                    'bundle' => 'Von den Bestandteilen dieses Bundles sind bereits Exemplare ausgegeben — bitte zuerst die Ausgabe rückgängig machen.',
                ]);
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

        $this->rentalBundles->delete($rentalBundle->id);
    }

    /**
     * Belegte Artikel eines gebuchten Bundles; Altbuchungen ohne Zuordnung
     * fallen auf die aktuellen Bestandteile (Hauptartikel) zurück.
     *
     * @return array<string, int>
     */
    public function allocationsOf(RentalBundle $rentalBundle): array
    {
        if ($rentalBundle->allocations !== []) {
            return $rentalBundle->allocations;
        }

        $allocations = [];
        foreach ($this->bundleComponents->findByBundle($rentalBundle->bundleArticleId) as $component) {
            $allocations[$component->componentArticleId] = ($allocations[$component->componentArticleId] ?? 0)
                + $component->quantity * $rentalBundle->quantity;
        }

        return $allocations;
    }

    private function plannedInRental(string $rentalId, string $articleId): int
    {
        return $rentalId !== '' ? ($this->rentalItems->findByRentalAndArticle($rentalId, $articleId)?->quantity ?? 0) : 0;
    }

    /** @return array<string, int> Artikel-ID => in überschneidenden anderen Vermietungen geplante Menge */
    private function usageInOverlappingRentals(string $rentalId, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        if ($this->rentals === null || $from === null || $to === null) {
            return [];
        }

        $usage = [];
        foreach ($this->rentals->findOverlapping($from, $to) as $rental) {
            if ($rental->id === $rentalId) {
                continue;
            }
            foreach ($this->rentalItems->findByRental($rental->id) as $item) {
                $usage[$item->articleId] = ($usage[$item->articleId] ?? 0) + $item->quantity;
            }
        }

        return $usage;
    }
}
