<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Settings\LegalSettings;

/**
 * Statischer, request-gebundener Speicher für die Rechtstext-Einstellungen,
 * analog zu Auth\CurrentUser — erlaubt View::page(), Impressum/Datenschutz-
 * Links im Footer (siehe layout.php) auf jeder Seite mitzuliefern, ohne dass
 * jeder Controller sie explizit durchreichen müsste. Wird einmal pro Request
 * in bootstrap.php gesetzt.
 */
final class LegalFooterLinks
{
    private static ?LegalSettings $settings = null;

    public static function set(?LegalSettings $settings): void
    {
        self::$settings = $settings;
    }

    public static function get(): ?LegalSettings
    {
        return self::$settings;
    }
}
