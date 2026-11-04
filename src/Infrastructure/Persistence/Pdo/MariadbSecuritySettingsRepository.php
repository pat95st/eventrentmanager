<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\SecuritySettings;
use App\Domain\Settings\SecuritySettingsRepositoryInterface;
use PDO;
use PDOException;

final class MariadbSecuritySettingsRepository implements SecuritySettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): SecuritySettings
    {
        // Wird bei JEDER Anfrage gelesen (Session-Timeout, siehe bootstrap.php).
        // Fehlt die Tabelle noch (Migration 127 nicht eingespielt), gilt der
        // Standardwert, statt die ganze Anwendung lahmzulegen.
        try {
            $row = $this->pdo->query('SELECT session_timeout_minutes FROM security_settings WHERE id = 1')->fetch();
        } catch (PDOException) {
            return new SecuritySettings();
        }

        return new SecuritySettings(
            sessionTimeoutMinutes: $row !== false ? (int) $row['session_timeout_minutes'] : SecuritySettings::DEFAULT_SESSION_TIMEOUT_MINUTES,
        );
    }

    public function save(SecuritySettings $settings): void
    {
        $this->pdo->prepare(
            'INSERT INTO security_settings (id, session_timeout_minutes) VALUES (1, :minutes)
             ON DUPLICATE KEY UPDATE session_timeout_minutes = VALUES(session_timeout_minutes)'
        )->execute(['minutes' => $settings->sessionTimeoutMinutes]);
    }
}
