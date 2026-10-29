<?php

declare(strict_types=1);

namespace App\Domain\Settings;

final class CompanySettings
{
    public function __construct(
        public ?string $name,
        public ?string $street,
        public ?string $postalCode,
        public ?string $city,
        public ?string $country,
        public ?string $logoFilename,
        public ?int $logoMaxHeightPx = null,
        public ?string $darkLogoFilename = null,
        // Rein kosmetischer, optionaler Anzeigename (siehe Migration 026) —
        // getrennt vom rechtlich verbindlichen $name (Impressum/Angebote/
        // Rechnungen). Wird z. B. als Alt-Text des Header-Logos verwendet,
        // wenn gesetzt, sonst greift weiterhin der App-Standardtext.
        public ?string $brandName = null,
        // Optionales Hintergrundbild für die gesamte Oberfläche (siehe
        // CompanyBranding::backgroundImageUrl()); ist eins hinterlegt,
        // werden die Karten/Panels in der Oberfläche halbtransparent
        // dargestellt (siehe resources/css/app.css, Abschnitt
        // "Hintergrundbild"), damit das Bild durchscheint.
        public ?string $backgroundImageFilename = null,
        // Kontaktdaten für die Fußzeile der Dokumente (Migration 108).
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $website = null,
    ) {
    }
}
