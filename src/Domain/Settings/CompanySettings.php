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
        // Migration 112: eigenes Favicon (Einstellungen › Firma)
        public ?string $faviconFilename = null,
        // Migration 126: Darstellung des Oberflächen-Hintergrunds sowie ein
        // eigener Hintergrund (Bild oder kurzes Video) für die Anmeldeseite.
        // $backgroundImageFilename kann seitdem auch ein Video sein.
        public BackgroundStyle $backgroundStyle = new BackgroundStyle(),
        public ?string $loginBackgroundFilename = null,
        public BackgroundStyle $loginBackgroundStyle = new BackgroundStyle(),
    ) {
    }

    public function backgroundFilename(BackgroundSlot $slot): ?string
    {
        return match ($slot) {
            BackgroundSlot::App => $this->backgroundImageFilename,
            BackgroundSlot::Login => $this->loginBackgroundFilename,
        };
    }

    public function setBackgroundFilename(BackgroundSlot $slot, ?string $filename): void
    {
        match ($slot) {
            BackgroundSlot::App => $this->backgroundImageFilename = $filename,
            BackgroundSlot::Login => $this->loginBackgroundFilename = $filename,
        };
    }

    public function backgroundStyle(BackgroundSlot $slot): BackgroundStyle
    {
        return match ($slot) {
            BackgroundSlot::App => $this->backgroundStyle,
            BackgroundSlot::Login => $this->loginBackgroundStyle,
        };
    }

    public function setBackgroundStyle(BackgroundSlot $slot, BackgroundStyle $style): void
    {
        match ($slot) {
            BackgroundSlot::App => $this->backgroundStyle = $style,
            BackgroundSlot::Login => $this->loginBackgroundStyle = $style,
        };
    }

    /** Ist der Hintergrund ein Video (MP4/WebM) statt eines Bildes? */
    public static function isVideoFilename(?string $filename): bool
    {
        return $filename !== null && in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), ['mp4', 'webm'], true);
    }
}
