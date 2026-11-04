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
        return self::versioned('/firmenlogo', self::$settings?->logoFilename);
    }

    /**
     * URL des optionalen Dunkelmodus-Logos, oder null falls keins hinterlegt
     * ist — dann bleibt in der Oberfläche auch im Dunkelmodus das Hell-Logo
     * sichtbar (siehe layout.php/auth-Views). Wird NIE für PDFs verwendet,
     * dort kommt ausschließlich logoUrl() (das Hell-/Standard-Logo) zum Einsatz.
     */
    public static function darkLogoUrl(): ?string
    {
        return self::versioned('/firmenlogo-dark', self::$settings?->darkLogoFilename);
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
        return self::versioned('/firmenhintergrund', self::$settings?->backgroundImageFilename);
    }

    /**
     * URL des eigenen Favicons (Migration 112) inkl. Cache-Buster, oder null
     * — dann gilt das Standard-Favicon public/favicon.ico.
     */
    public static function faviconUrl(): ?string
    {
        $filename = self::$settings?->faviconFilename;

        return self::versioned('/firmen-favicon', $filename);
    }

    /**
     * Manifest der installierten App: mit eigenem Favicon das dynamische
     * Manifest (Favicon als App-Icon, siehe CompanySettingsController::
     * appManifest()), sonst die statische Datei public/manifest.webmanifest.
     */
    public static function manifestUrl(): string
    {
        return self::versioned('/app.webmanifest', self::$settings?->faviconFilename) ?? '/manifest.webmanifest';
    }

    /** Apple-Touch-Icon (Startbildschirm iOS): aus dem Favicon, außer bei SVG (braucht ein Rasterbild). */
    public static function appleTouchIconUrl(): string
    {
        $filename = self::$settings?->faviconFilename;
        if ($filename === null || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'svg') {
            return '/assets/img/icons/apple-touch-icon.png';
        }

        return self::versioned('/app-icon/apple', $filename) ?? '/assets/img/icons/apple-touch-icon.png';
    }

    /**
     * URL mit Versionsparameter aus dem gespeicherten Dateinamen (bei jedem
     * Upload eine neue UUID): so darf der Browser/die PWA die Datei dauerhaft
     * cachen und lädt sie erst nach einem neuen Upload wieder (siehe
     * Response::cachedFile()).
     */
    private static function versioned(string $path, ?string $filename): ?string
    {
        return $filename !== null ? $path . '?v=' . self::version($filename) : null;
    }

    /** Kurzer Versions-Hash eines gespeicherten Dateinamens, auch für den Abgleich im Controller. */
    public static function version(string $filename): string
    {
        return substr(md5($filename), 0, 8);
    }
}
