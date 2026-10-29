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
    ) {
    }

    /** Menge eines Artikels, die in dieser Vermietung durch gebuchte Bundles belegt ist. */
    public function requiredQuantity(string $rentalId, string $articleId): int
    {
        $required = 0;
        foreach ($this->rentalBundles->findByRental($rentalId) as $rentalBundle) {
            foreach ($this->bundleComponents->findByBundle($rentalBundle->bundleArticleId) as $component) {
                if ($component->componentArticleId === $articleId) {
                    $required += $component->quantity * $rentalBundle->quantity;
                }
            }
        }

        return $required;
    }

    /**
     * Prüft, ob $quantity Bundles in die Vermietung passen (Inventarnummern
     * je Bestandteil), ohne etwas zu speichern.
     *
     * @param array<string, int> $alsoPlanned zusätzlich bereits eingeplante Mengen je Artikel (z. B. andere Positionen derselben Umwandlung)
     * @return array<string, int> zusätzlicher Bedarf je Bestandteil-Artikel
     */
    public function checkAdd(string $rentalId, Article $bundle, int $quantity, array $alsoPlanned = []): array
    {
        $components = $this->bundleComponents->findByBundle($bundle->id);
        if ($components === []) {
            throw new ValidationException(['articleId' => "Das Bundle „{$bundle->name}“ hat keine Bestandteile und kann daher nicht vermietet werden."]);
        }

        $demand = [];
        foreach ($components as $component) {
            $demand[$component->componentArticleId] = ($demand[$component->componentArticleId] ?? 0) + $component->quantity * $quantity;
        }

        foreach ($demand as $articleId => $additional) {
            $existing = $this->rentalItems->findByRentalAndArticle($rentalId, $articleId)?->quantity ?? 0;
            $available = count($this->inventoryNumbers->findActiveByArticle($articleId));
            if ($existing + ($alsoPlanned[$articleId] ?? 0) + $additional > $available) {
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
            $rentalBundle->quantity += $quantity;
            $rentalBundle->updatedAt = $now;
        } else {
            $rentalBundle = new RentalBundle(
                id: Uuid::v4(),
                rentalId: $rentalId,
                bundleArticleId: $bundle->id,
                quantity: $quantity,
                createdAt: $now,
                updatedAt: $now,
            );
        }
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
        foreach ($this->bundleComponents->findByBundle($rentalBundle->bundleArticleId) as $component) {
            $item = $this->rentalItems->findByRentalAndArticle($rentalBundle->rentalId, $component->componentArticleId);
            if ($item === null) {
                continue;
            }
            $newQuantity = $item->quantity - $component->quantity * $rentalBundle->quantity;
            $issued = $this->issuedItems->countByRentalAndArticle($rentalBundle->rentalId, $component->componentArticleId);
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
}
