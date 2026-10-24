<?php

declare(strict_types=1);

namespace App\Domain\Purchase;

interface PurchaseOrderRepositoryInterface
{
    public function save(PurchaseOrder $order): void;

    public function findById(string $id): ?PurchaseOrder;

    /**
     * @return PurchaseOrder[] sortiert nach Erstellungsdatum absteigend (neueste zuerst), höchstens $limit Zeilen ab $offset
     */
    public function search(?string $searchTerm, ?PurchaseOrderStatus $status, int $limit, int $offset): array;

    /** Gesamtanzahl der Treffer für dieselben Filter wie search(), ohne LIMIT/OFFSET (für die Paginierung). */
    public function countSearch(?string $searchTerm, ?PurchaseOrderStatus $status): int;

    public function delete(string $id): void;

    /** @return PurchaseOrderItem[] */
    public function findItemsByOrder(string $purchaseOrderId): array;

    public function findItemById(string $id): ?PurchaseOrderItem;

    public function saveItem(PurchaseOrderItem $item): void;

    /**
     * Ersetzt sämtliche Positionen einer Bestellung durch die übergebene
     * Liste (wie OfferRepositoryInterface::replaceItems()) — nur erlaubt,
     * solange die Bestellung noch im Entwurf ist (siehe
     * UpdatePurchaseOrderHandler), da ein Ersetzen sonst bereits erfasste
     * Wareneingänge (receivedQuantity) verwaisen ließe.
     *
     * @param PurchaseOrderItem[] $items
     */
    public function replaceItems(string $purchaseOrderId, array $items): void;
}
