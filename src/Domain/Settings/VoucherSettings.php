<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Singleton-Einstellung analog zu PricingSettings: die Standard-
 * Gültigkeitsdauer neu angelegter Gutscheine in Tagen, admin-editierbar
 * unter /einstellungen/gutscheine. Wird beim Anlegen eines Gutscheins ohne
 * explizites Gültig-bis-Datum als Vorgabe verwendet (siehe
 * CreateVoucherHandler) — eine spätere Änderung dieser Einstellung wirkt
 * sich NICHT rückwirkend auf bereits angelegte Gutscheine aus, da deren
 * validUntil bereits konkret auf dem Gutschein selbst gespeichert ist.
 */
final class VoucherSettings
{
    public function __construct(
        public int $defaultValidityDays,
    ) {
    }
}
