<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Settings\CompanySettings;

/**
 * Request-weiter Zugriff auf die Firmeneinstellungen (aktuell nur für das
 * Logo benötigt), analog zu CurrentUser/LegalFooterLinks: einmal pro
 * Request in bootstrap.php gesetzt, danach von jeder View lesbar — auch von
 * den Auth-Views (Login/Zwei-Faktor), die ohne layout.php gerendert werden
 * und sonst keinen Zugriff auf Firmeneinstellungen hätten.
 */
final class CompanyBranding
{
    private static ?CompanySettings $settings = null;

    public static function set(CompanySettings $settings): void
    {
        self::$settings = $settings;
    }

    public static function get(): ?CompanySettings
    {
        return self::$settings;
    }

    /** URL des öffentlich abrufbaren Firmenlogos, oder null falls keins hinterlegt ist. */
    public static function logoUrl(): ?string
    {
        return self::$settings?->logoFilename !== null ? '/firmenlogo' : null;
    }

    /**
     * URL des optionalen Dunkelmodus-Logos, oder null falls keins hinterlegt
     * ist — dann bleibt in der Oberfläche auch im Dunkelmodus das Hell-Logo
     * sichtbar (siehe layout.php/auth-Views). Wird NIE für PDFs verwendet,
     * dort kommt ausschließlich logoUrl() (das Hell-/Standard-Logo) zum Einsatz.
     */
    public static function darkLogoUrl(): ?string
    {
        return self::$settings?->darkLogoFilename !== null ? '/firmenlogo-dark' : null;
    }

    /** Konfigurierte maximale Logo-Höhe im Header in Pixeln, oder der Standardwert (40px), falls nichts hinterlegt ist. */
    public static function logoMaxHeightPx(): int
    {
        return self::$settings?->logoMaxHeightPx ?? 40;
    }

    /**
     * Optionaler, rein kosmetischer Markenname (z. B. für den Alt-Text des
     * Header-Logos), getrennt vom rechtlich verbindlichen Firmennamen. Null,
     * wenn nichts hinterlegt ist — Aufrufer fallen dann auf t('nav.brand') zurück.
     */
    public static function brandName(): ?string
    {
        $trimmed = self::$settings?->brandName !== null ? trim(self::$settings->brandName) : null;

        return $trimmed !== null && $trimmed !== '' ? $trimmed : null;
    }

    /**
     * URL des optionalen Hintergrundbilds für die gesamte Oberfläche, oder
     * null falls keins hinterlegt ist (siehe layout.php, Abschnitt
     * "Hintergrundbild" in resources/css/app.css).
     */
    public static function backgroundImageUrl(): ?string
    {
        return self::$settings?->backgroundImageFilename !== null ? '/firmenhintergrund' : null;
    }
}
