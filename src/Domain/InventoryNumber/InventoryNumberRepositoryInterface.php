<?php

declare(strict_types=1);

namespace App\Domain\InventoryNumber;

interface InventoryNumberRepositoryInterface
{
    public function save(InventoryNumber $inventoryNumber): void;

    public function findById(string $id): ?InventoryNumber;

    /** Für den Barcode-Scanner-Workflow bei der Ausgabe: exakte Suche nach der Nummer selbst. */
    public function findByNumber(string $number): ?InventoryNumber;

    /** @return InventoryNumber[] */
    public function findByArticle(string $articleId): array;

    /**
     * Wie findByArticle(), aber OHNE bereits verkaufte Exemplare (siehe
     * App\Domain\Sale\Sale) — für Verfügbarkeits-/Bestandsberechnungen bei
     * Vermietung/Ausgabe. findByArticle() selbst bleibt unverändert (liefert
     * weiterhin ALLE Zeilen, u. a. für die Nummernvergabe und die normale
     * Inventarliste, die verkaufte Exemplare deutlich markiert anzeigt).
     *
     * @return InventoryNumber[]
     */
    public function findActiveByArticle(string $articleId): array;

    /** @return InventoryNumber[] */
    public function findByReceipt(string $receiptId): array;

    public function existsByNumber(string $number, ?string $excludeId = null): bool;

    /** @return InventoryNumber[] alle Inventurnummern mit gesetzter installierter Firmware-Version, für die Update-Übersicht (siehe ListFirmwareUpdatesAvailableHandler). */
    public function findAllWithInstalledFirmwareVersion(): array;

    /** Wird diese Firmware-Version aktuell auf irgendeiner Inventurnummer als installiert geführt? Sperrt ggf. das Löschen (siehe DeleteFirmwareVersionHandler). */
    public function existsByInstalledFirmwareVersion(string $firmwareVersionId): bool;

    public function delete(string $id): void;
}
