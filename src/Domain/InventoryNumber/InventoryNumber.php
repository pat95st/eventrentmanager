<?php

declare(strict_types=1);

namespace App\Domain\InventoryNumber;

use DateTimeImmutable;

final class InventoryNumber
{
    public function __construct(
        public readonly string $id,
        public readonly string $articleId,
        public string $number,
        public ?DateTimeImmutable $acquisitionDate,
        public ?string $supplierId, // Lieferant, von dem dieses Exemplar bezogen wurde (Kauf/Zumietung); optional
        public ?string $receiptId, // Beleg, über den dieses Exemplar bezogen wurde; optional. Wenn gesetzt, wird der
        // Lieferant NICHT redundant hier gespeichert (supplierId bleibt dann null), sondern über den
        // verknüpften Beleg aufgelöst (Single Source of Truth, siehe AddInventoryNumberHandler).
        public ?string $purchasePriceOverride, // eigener Einkaufspreis, falls abweichend vom Artikel-Einkaufspreis; optional
        public ?string $storageLocation, // Freitext-Lagerort dieses Exemplars, z. B. "Regal 3, Fach B"; optional
        public ?string $installedFirmwareVersionId, // installierte Firmware-Version (FK auf FirmwareVersion); nur relevant, wenn der zugehörige Artikel hasFirmware ist. Bewusst eine Auswahl aus dem Artikel-Katalog (kein Freitext), damit der Update-verfügbar-Vergleich immer über einen exakten ID-Abgleich funktioniert
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /** Eigener Preis, falls gesetzt, sonst der übergebene Artikel-Einkaufspreis. */
    public function effectivePurchasePrice(?string $articlePurchasePrice): ?string
    {
        return $this->purchasePriceOverride ?? $articlePurchasePrice;
    }
}
