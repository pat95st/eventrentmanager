<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\VoucherSettings;
use App\Domain\Settings\VoucherSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbVoucherSettingsRepository implements VoucherSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): VoucherSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM voucher_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            // Sollte durch die Migration/das Schema nie vorkommen (die
            // Singleton-Zeile wird dort direkt mit angelegt), aber ein
            // klarer Fehler ist hier besser als eine stille Falscheinstellung.
            throw new RuntimeException('Gutschein-Einstellungen wurden nicht gefunden — fehlt die Migration 074?');
        }

        return new VoucherSettings(
            defaultValidityDays: (int) $row['default_validity_days'],
        );
    }

    public function save(VoucherSettings $settings): void
    {
        $stmt = $this->pdo->prepare('UPDATE voucher_settings SET default_validity_days = :default_validity_days WHERE id = 1');
        $stmt->execute(['default_validity_days' => $settings->defaultValidityDays]);
    }
}
