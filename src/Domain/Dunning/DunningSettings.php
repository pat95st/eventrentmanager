<?php

declare(strict_types=1);

namespace App\Domain\Dunning;

/**
 * Firmenweite Mahnwesen-Einstellung (Singleton, analog InvoiceSettings/
 * CompanySettings). $autoSendEnabled steuert bin/send-dunning-notices.php:
 * nur wenn true, verschickt der Cron-Lauf automatisch Mahnungen für
 * überfällige Rechnungen — sonst können Mahnungen ausschließlich manuell
 * über die Rechnungs-Detailansicht erstellt werden (siehe DunningController).
 */
final class DunningSettings
{
    public function __construct(
        public bool $autoSendEnabled = false,
    ) {
    }
}
