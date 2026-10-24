<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\GoogleBusinessSettings;
use App\Domain\Settings\GoogleBusinessSettingsRepositoryInterface;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class MariadbGoogleBusinessSettingsRepository implements GoogleBusinessSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): GoogleBusinessSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM google_business_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            throw new RuntimeException('Google-Business-Einstellungen wurden nicht gefunden — fehlt die entsprechende Migration?');
        }

        return new GoogleBusinessSettings(
            clientId: $row['client_id'],
            clientSecret: $row['client_secret'],
            accessToken: $row['access_token'],
            refreshToken: $row['refresh_token'],
            accessTokenExpiresAt: $row['access_token_expires_at'] !== null ? new DateTimeImmutable($row['access_token_expires_at']) : null,
            accountId: $row['account_id'],
            locationId: $row['location_id'],
            connectedAt: $row['connected_at'] !== null ? new DateTimeImmutable($row['connected_at']) : null,
        );
    }

    public function save(GoogleBusinessSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE google_business_settings SET
                client_id = :client_id,
                client_secret = :client_secret,
                access_token = :access_token,
                refresh_token = :refresh_token,
                access_token_expires_at = :access_token_expires_at,
                account_id = :account_id,
                location_id = :location_id,
                connected_at = :connected_at
             WHERE id = 1'
        );

        $stmt->execute([
            'client_id' => $settings->clientId,
            'client_secret' => $settings->clientSecret,
            'access_token' => $settings->accessToken,
            'refresh_token' => $settings->refreshToken,
            'access_token_expires_at' => $settings->accessTokenExpiresAt?->format('Y-m-d H:i:s'),
            'account_id' => $settings->accountId,
            'location_id' => $settings->locationId,
            'connected_at' => $settings->connectedAt?->format('Y-m-d H:i:s'),
        ]);
    }
}
