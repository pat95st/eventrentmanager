<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Settings\BackgroundSlot;
use App\Domain\Settings\BackgroundStyle;
use App\Domain\Settings\CompanySettings;

/**
 * HTML für einen Hintergrund (Bild oder Video, Migration 126): eine feste
 * Ebene hinter dem gesamten Seiteninhalt. Deckkraft, Einpassung, Ausschnitt
 * und Zoom kommen aus BackgroundStyle. Die Ebene liegt mit z-index -1 über
 * der Grundfarbe (auf <html> gesetzt) — so scheint bei geringer Deckkraft die
 * helle bzw. dunkle Grundfarbe durch.
 *
 * Verwendet in layout.php (Oberfläche), den Anmelde-/Zwei-Faktor-Seiten und
 * als Vorschau in Einstellungen › Firma.
 */
final class BackgroundLayer
{
    /** Hintergrund des Bereichs $slot, oder '' wenn keiner hinterlegt ist. */
    public static function render(BackgroundSlot $slot): string
    {
        $url = CompanyBranding::backgroundUrl($slot);
        if ($url === null) {
            return '';
        }

        return self::styles() . self::layer($url, CompanyBranding::backgroundIsVideo($slot), CompanyBranding::backgroundStyle($slot));
    }

    /** CSS-Klasse für <body>, damit Karten/Leisten halbtransparent werden (resources/css/app.css). */
    public static function bodyClass(BackgroundSlot $slot): string
    {
        return CompanyBranding::backgroundUrl($slot) !== null ? ' has-bg-image' : '';
    }

    /**
     * Die Ebene selbst. $preview: in einem Vorschau-Kasten (position:absolute)
     * statt fest hinter der ganzen Seite.
     */
    public static function layer(string $url, bool $video, BackgroundStyle $style, bool $preview = false, string $id = ''): string
    {
        $position = $style->cssPosition();
        $transform = $style->zoom !== 100
            ? 'transform:scale(' . self::number($style->zoom / 100) . ');transform-origin:' . $position . ';'
            : 'transform-origin:' . $position . ';';
        $attributes = ($id !== '' ? ' id="' . self::esc($id) . '"' : '')
            . ' class="bg-media-layer' . ($preview ? ' is-preview' : '') . '" aria-hidden="true"'
            . ' style="opacity:' . self::number($style->opacity / 100) . '"';

        if ($video) {
            $media = '<video class="bg-media" src="' . self::esc($url) . '" muted autoplay loop playsinline preload="auto" disablepictureinpicture data-bg-video'
                . ' style="object-fit:' . $style->fit . ';object-position:' . $position . ';' . $transform . '"></video>'
                // Bei "Bewegung reduzieren" (Betriebssystem-Einstellung) bleibt das Video stehen.
                . '<script>(function(){var v=document.currentScript.previousElementSibling;'
                . 'if(window.matchMedia&&matchMedia("(prefers-reduced-motion: reduce)").matches){v.removeAttribute("autoplay");v.pause();}})();</script>';
        } else {
            $media = '<div class="bg-media" style="background-image:url(\'' . self::esc($url) . '\');background-size:' . $style->fit
                . ';background-position:' . $position . ';' . $transform . '"></div>';
        }

        return '<div' . $attributes . '>' . $media . '</div>';
    }

    /** Einmalige CSS-Regeln für die Hintergrund-Ebene. */
    public static function styles(): string
    {
        return '<style>'
            . 'html{background-color:#f8fafc}html.dark{background-color:#020617}'
            . 'body.has-bg-image{background-color:transparent!important;background-image:none!important}'
            . '.bg-media-layer{position:fixed;inset:0;z-index:-1;overflow:hidden;pointer-events:none}'
            . '.bg-media-layer.is-preview{position:absolute;z-index:0}'
            . '.bg-media{position:absolute;inset:0;display:block;width:100%;height:100%;background-repeat:no-repeat}'
            . '</style>';
    }

    /** Für das Formular der Einstellungen: Bild oder Video? */
    public static function isVideo(?string $filename): bool
    {
        return CompanySettings::isVideoFilename($filename);
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
