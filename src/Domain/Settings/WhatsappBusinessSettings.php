<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Zugangsdaten für den WhatsApp-Business-Produktkatalog (Meta Commerce
 * Manager, über die Graph API angesprochen). WhatsApp selbst hat keinen
 * "API-Key" für Kataloge — benötigt werden ein dauerhaftes System-User-
 * Access-Token sowie die Katalog-ID, siehe
 * App\Infrastructure\Integration\Whatsapp\WhatsappCatalogApiClient.
 */
final class WhatsappBusinessSettings
{
    public function __construct(
        public ?string $accessToken = null,
        public ?string $catalogId = null,
        public string $currency = 'EUR',
    ) {
    }
}
