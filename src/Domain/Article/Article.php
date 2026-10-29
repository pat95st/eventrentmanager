<?php

declare(strict_types=1);

namespace App\Domain\Article;

use DateTimeImmutable;

final class Article
{
    /**
     * @param ArticleImage[] $images
     */
    public function __construct(
        public readonly string $id,
        public string $name,
        public string $manufacturerId,
        public ?string $prefix, // Präfix für generierte Inventurnummern, z. B. "AKS"; bei Altdaten ggf. noch null
        public ?string $categoryId,
        public ?string $description,
        public ?string $weightValue,   // als String geführt, um Rundungsfehler bei DECIMAL zu vermeiden
        public ?string $weightUnit,    // 'g' | 'kg' | null
        public ?string $dimensions,
        public bool $is19Inch, // 19-Zoll-Gerät (rack-montierbar)?
        public ?int $heightUnits, // Höheneinheiten (HE), z. B. 3 für ein 3-HE-Gerät; nur relevant, wenn is19Inch
        public ?string $installDepthCm, // Einbautiefe in cm, als String geführt (siehe weightValue); nur relevant, wenn is19Inch
        public ?string $purchasePrice, // Einkaufspreis in Euro, als String geführt (siehe weightValue); optional
        public bool $requiresElectricalInspection, // Elektroprüfung (z. B. DGUV V3) nötig?
        public ?ProtectionClass $protectionClass, // Schutzklasse I/II/III; nur gepflegt, wenn requiresElectricalInspection = true, sonst immer null
        public bool $hasFirmware, // Gerät besitzt eine aktualisierbare Firmware? Nur dann werden Firmware-Versionen (siehe FirmwareVersion) für diesen Artikel verwaltet
        public bool $isBillable, // bei einer Vermietung als eigener Posten abgerechnet (sonst Zubehör, z. B. Kabel)
        public bool $isActive, // deaktivierte Artikel bleiben in der Artikelverwaltung sichtbar, verschwinden aber aus der Artikelauswahl (siehe ListArticlesHandler, Parameter onlyActive)
        public ?string $dailyRentalPrice, // Preis pro Einsatztag in Euro, als String geführt (siehe purchasePrice); nur relevant, wenn isBillable
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public array $images = [],
        // Nutzerwunsch Bundles (Migration 107): false = darf in Vermietungen
        // und Miet-Positionen von Dokumenten nicht einzeln gewählt werden,
        // nur als Bestandteil eines Bundles (z. B. passiver Lautsprecher, der
        // nur mit passender Endstufe vermietet wird).
        public bool $isRentableIndividually = true,
        // true = Artikel ist ein Bundle (eigener Name/Tagesmietsatz,
        // Bestandteile siehe ArticleBundleComponent); hat keinen eigenen
        // physischen Bestand/Inventarnummern.
        public bool $isBundle = false,
    ) {
    }
}
