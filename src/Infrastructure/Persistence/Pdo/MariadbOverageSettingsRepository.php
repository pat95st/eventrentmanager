<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\OverageSettings;
use App\Domain\Settings\OverageSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbOverageSettingsRepository implements OverageSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): OverageSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM overage_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            // Sollte durch die Migration nie vorkommen (die Singleton-Zeile
            // wird dort direkt mit angelegt), siehe MariadbInvoiceSettingsRepository
            // für das identische Muster.
            throw new RuntimeException('Überziehungs-Einstellungen wurden nicht gefunden — fehlt die Migration 068?');
        }

        return new OverageSettings(
            flatFeeEnabled: (bool) $row['flat_fee_enabled'],
            flatFeeAmount: $row['flat_fee_amount'],
        );
    }

    public function save(OverageSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE overage_settings SET flat_fee_enabled = :flat_fee_enabled, flat_fee_amount = :flat_fee_amount WHERE id = 1'
        );

        $stmt->execute([
            'flat_fee_enabled' => $settings->flatFeeEnabled ? 1 : 0,
            'flat_fee_amount' => $settings->flatFeeAmount,
        ]);
    }
}
