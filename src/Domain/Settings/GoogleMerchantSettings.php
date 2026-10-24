<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Zugangsdaten/Konfiguration für die Google Merchant API (Content API for
 * Shopping), siehe App\Infrastructure\Integration\Google\GoogleMerchantApiClient.
 * Die Authentifizierung läuft über einen Google-Service-Account (JSON-
 * Schlüsseldatei aus der Google Cloud Console), nicht über einen einfachen
 * API-Key — siehe $serviceAccountJsonFilename.
 */
final class GoogleMerchantSettings
{
    public function __construct(
        // Merchant-Center-Konto-ID (Ziffernfolge, siehe Google Merchant Center > Kontoeinstellungen).
        public ?string $merchantId = null,
        // Dateiname der hochgeladenen Service-Account-JSON-Schlüsseldatei unterhalb von
        // storage/uploads/integrations/google-merchant/ (siehe LocalFileStorage).
        public ?string $serviceAccountJsonFilename = null,
        public string $targetCountry = 'DE',
        public string $contentLanguage = 'de',
        // URL-Vorlage für die Produktlandingpage, die Google für jedes Produkt zwingend
        // benötigt (Pflichtfeld "link"). Enthält den Platzhalter {id}, der durch die
        // Artikel-ID ersetzt wird, z. B. "https://verleih.example.de/produkte/{id}" —
        // ohne eigene öffentliche Website kann stattdessen die integrierte, öffentliche
        // Produktseite dieser Anwendung verwendet werden (siehe PublicProductController).
        public ?string $productLinkTemplate = null,
    ) {
    }
}
