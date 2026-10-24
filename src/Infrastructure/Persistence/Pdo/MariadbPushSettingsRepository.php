<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Push\PushSettings;
use App\Domain\Push\PushSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbPushSettingsRepository implements PushSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): PushSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM push_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            throw new RuntimeException('Push-Einstellungen wurden nicht gefunden — fehlt die Migration 043?');
        }

        return new PushSettings(
            vapidPublicKey: $row['vapid_public_key'],
            vapidPrivateKeyPem: $row['vapid_private_key_pem'],
            subjectEmail: $row['subject_email'],
        );
    }

    public function save(PushSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE push_settings SET vapid_public_key = :vapid_public_key, vapid_private_key_pem = :vapid_private_key_pem, subject_email = :subject_email
             WHERE id = 1'
        );

        $stmt->execute([
            'vapid_public_key' => $settings->vapidPublicKey,
            'vapid_private_key_pem' => $settings->vapidPrivateKeyPem,
            'subject_email' => $settings->subjectEmail,
        ]);
    }
}
