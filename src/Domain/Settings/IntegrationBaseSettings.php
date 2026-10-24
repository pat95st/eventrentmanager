<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Öffentlich erreichbare Basis-URL dieser Installation, z. B.
 * "https://verleih.example.de" (ohne abschließenden Schrägstrich). Wird für
 * drei Dinge gebraucht, die alle eine absolute, von außen erreichbare URL
 * benötigen:
 *  - den Produktlink, den Google Merchant Center für jedes Produkt fordert
 *    (siehe GoogleMerchantSettings::$productLinkTemplate),
 *  - den Bildlink für Google Merchant Center / WhatsApp-Katalog
 *    (öffentliche Artikelbild-Route, siehe PublicProductController),
 *  - die OAuth-Redirect-URL beim Verbinden mit Google Unternehmensprofil
 *    (siehe GoogleBusinessController::connect()).
 */
final class IntegrationBaseSettings
{
    public function __construct(
        public ?string $baseUrl = null,
    ) {
    }
}
