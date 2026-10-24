<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use DateTimeImmutable;

/**
 * Zugangsdaten/Verbindung zur Google Business Profile API (früher "Google
 * My Business"). Anders als Merchant Center läuft die Authentifizierung
 * hier per OAuth2-Nutzerfreigabe (3-legged), nicht per Service-Account —
 * siehe GoogleBusinessController::connect()/callback() sowie
 * App\Infrastructure\Integration\Google\GoogleBusinessApiClient.
 *
 * WICHTIG: Google stellt für das Anlegen einzelner "Produkte" im
 * Unternehmensprofil keine öffentlich nutzbare API bereit. Die Anbindung
 * legt daher pro freigeschaltetem Artikel einen "Beitrag" (Local Post) mit
 * Titel/Bild/Link zum Artikel an — siehe GoogleBusinessChannelPublisher.
 */
final class GoogleBusinessSettings
{
    public function __construct(
        // Aus der Google Cloud Console (OAuth-Client "Web-Anwendung").
        public ?string $clientId = null,
        public ?string $clientSecret = null,
        public ?string $accessToken = null,
        public ?string $refreshToken = null,
        public ?DateTimeImmutable $accessTokenExpiresAt = null,
        // Resource-Name des Google-Business-Profile-Kontos, z. B. "accounts/1234567890".
        public ?string $accountId = null,
        // Resource-Name des Standorts, z. B. "accounts/1234567890/locations/9876543210".
        public ?string $locationId = null,
        public ?DateTimeImmutable $connectedAt = null,
    ) {
    }
}
