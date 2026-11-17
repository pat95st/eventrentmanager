<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\ConfirmCatalog;
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
            $row = $this->pdo->query('SELECT * FROM security_settings WHERE id = 1')->fetch();
        } catch (PDOException) {
            return new SecuritySettings();
        }
        if ($row === false) {
            return new SecuritySettings();
        }

        // The confirm columns are missing until migration 174 has run.
        $overrides = [];
        $decoded = json_decode((string) ($row['confirm_overrides'] ?? ''), true);
        if (is_array($decoded)) {
            foreach ($decoded as $key => $required) {
                if (is_string($key) && ConfirmCatalog::has($key)) {
                    $overrides[$key] = (bool) $required;
                }
            }
        }

        return new SecuritySettings(
            sessionTimeoutMinutes: (int) $row['session_timeout_minutes'],
            confirmBypassEnabled: (bool) ($row['confirm_bypass_enabled'] ?? true),
            confirmOverrides: $overrides,
        );
    }

    public function save(SecuritySettings $settings): void
    {
        $this->pdo->prepare(
            'INSERT INTO security_settings (id, session_timeout_minutes, confirm_bypass_enabled, confirm_overrides)
             VALUES (1, :minutes, :bypass, :overrides)
             ON DUPLICATE KEY UPDATE session_timeout_minutes = VALUES(session_timeout_minutes),
                confirm_bypass_enabled = VALUES(confirm_bypass_enabled), confirm_overrides = VALUES(confirm_overrides)'
        )->execute([
            'minutes' => $settings->sessionTimeoutMinutes,
            'bypass' => $settings->confirmBypassEnabled ? 1 : 0,
            'overrides' => $settings->confirmOverrides === [] ? null : json_encode($settings->confirmOverrides, JSON_THROW_ON_ERROR),
        ]);
    }
}
