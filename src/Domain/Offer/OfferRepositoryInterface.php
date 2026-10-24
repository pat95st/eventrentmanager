<?php

declare(strict_types=1);

namespace App\Domain\Offer;

interface OfferRepositoryInterface
{
    public function save(Offer $offer): void;

    public function findById(string $id): ?Offer;

    /**
     * @return Offer[] sortiert nach Erstellungsdatum absteigend (neueste zuerst), höchstens $limit Zeilen ab $offset
     */
    public function search(?string $searchTerm, ?OfferStatus $status, int $limit, int $offset): array;

    /** Gesamtanzahl der Treffer für dieselben Filter wie search(), ohne LIMIT/OFFSET (für die Paginierung). */
    public function countSearch(?string $searchTerm, ?OfferStatus $status): int;

    public function delete(string $id): void;

    /** @return OfferItem[] */
    public function findItemsByOffer(string $offerId): array;

    /**
     * Ersetzt sämtliche Positionen eines Angebots durch die übergebene Liste
     * (Angebotspositionen werden immer als Ganzes über das Formular
     * gespeichert, siehe offers/form.php — kein separates Hinzufügen/
     * Entfernen einzelner Zeilen wie bei RentalItem).
     *
     * @param OfferItem[] $items
     */
    public function replaceItems(string $offerId, array $items): void;

    /** @return OfferLaborItem[] */
    public function findLaborItemsByOffer(string $offerId): array;

    /**
     * Ersetzt sämtliche Arbeitszeit-Positionen eines Angebots durch die
     * übergebene Liste (identisches "immer als Ganzes speichern"-Muster wie
     * replaceItems()).
     *
     * @param OfferLaborItem[] $items
     */
    public function replaceLaborItems(string $offerId, array $items): void;

    /** @return OfferSaleItem[] */
    public function findSaleItemsByOffer(string $offerId): array;

    /**
     * Ersetzt sämtliche Verkaufspositionen eines Angebots durch die
     * übergebene Liste (identisches "immer als Ganzes speichern"-Muster wie
     * replaceItems()) — vom Aufrufer (UpdateOfferHandler) nur aufzurufen,
     * wenn keine der bisherigen Positionen bereits abgerechnet ist (siehe
     * OfferSaleItem::$fulfilledSaleId).
     *
     * @param OfferSaleItem[] $items
     */
    public function replaceSaleItems(string $offerId, array $items): void;

    /** Markiert eine einzelne Verkaufsposition als abgerechnet (siehe CreateInvoiceFromOfferHandler), ohne die übrigen Positionen anzufassen. */
    public function markSaleItemFulfilled(string $saleItemId, string $saleId): void;
}
