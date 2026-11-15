<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandInterface;

final class UpdateArticle implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $manufacturerId,
        public readonly string $prefix,
        public readonly ?string $categoryId,
        public readonly ?string $description,
        public readonly ?string $weightValue,
        public readonly ?string $weightUnit,
        public readonly ?string $dimensions,
        public readonly ?string $purchasePrice = null,
        public readonly bool $requiresElectricalInspection = false,
        public readonly ?string $protectionClass = null,
        public readonly bool $isBillable = true,
        public readonly ?string $dailyRentalPrice = null,
        public readonly bool $is19Inch = false,
        public readonly ?string $heightUnits = null,
        public readonly ?string $installDepthCm = null,
        public readonly bool $hasFirmware = false,
        // Bundles (Migration 107): nicht einzeln vermietbar / ist Bundle +
        // rohe Bestandteil-Zeilen aus dem Formular ([article_id, quantity]).
        public readonly bool $isRentableIndividually = true,
        public readonly bool $isBundle = false,
        /** @var array<int, array{article_id: string, quantity: string}> */
        public readonly array $bundleComponents = [],
        // Migration 109: Kurztext für Dokumente (null = Feld nicht übermittelt).
        public readonly ?string $documentShortText = null,
        // Stromverteiler (Migration 117/118): Angaben aus dem Artikelformular,
        // gespeichert nur bei Kategorie "Stromverteiler" (sonst entfernt);
        // false = kein Stromverteiler (mehr), null = unverändert lassen.
        public readonly \App\Application\PowerDistributor\PowerDistributorInput|false|null $powerDistributor = null,
        // Leitungen (Migration 125): null = Formular ohne Leitungs-Abschnitt (unverändert lassen).
        public readonly ?\App\Application\Cable\CableInput $cable = null,
        // Migration 128: Einbaubreite eines Rackeinbaugeräts ('19' | '9.5'); null = nicht übermittelt (unverändert/19 Zoll).
        public readonly ?string $rackWidth = null,
        // Digitale Stagebox (Migration 152): null = Formular ohne Stagebox-Abschnitt (unverändert lassen);
        // gespeichert nur bei Kategorie "Digitale Stagebox", sonst entfernt.
        public readonly ?\App\Application\DigitalStagebox\DigitalStageboxInput $digitalStagebox = null,
    ) {
    }
}
