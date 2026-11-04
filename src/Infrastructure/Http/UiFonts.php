<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Settings\PdfFont;
use App\Domain\Settings\UiFontRole;

/**
 * Schriftarten der Oberfläche je Bereich (Einstellungen › Schriftarten).
 * Einmal pro Anfrage in bootstrap.php gesetzt, ausgegeben in layout.php und
 * den Anmelde-Seiten als <style> mit @font-face und Zuordnungen.
 *
 * Die Schriftdateien liefert /schriften/{id}/{schnitt} aus (öffentlich, aber
 * nur für Schriften, die einem Oberflächen-Bereich zugeordnet sind — die
 * Anmeldeseite braucht sie vor der Anmeldung).
 */
final class UiFonts
{
    /** @var array<string, PdfFont> Bereich => Schrift */
    private static array $fonts = [];

    private const STYLES = [
        'regular' => [400, 'normal'],
        'bold' => [700, 'normal'],
        'italic' => [400, 'italic'],
        'boldItalic' => [700, 'italic'],
    ];

    /** @param array<string, PdfFont> $fonts */
    public static function set(array $fonts): void
    {
        self::$fonts = $fonts;
    }

    public static function fontFor(UiFontRole $role): ?PdfFont
    {
        return self::$fonts[$role->value] ?? null;
    }

    /** Ist die Schrift irgendeinem Oberflächen-Bereich zugeordnet (darf öffentlich ausgeliefert werden)? */
    public static function isPublic(string $fontId): bool
    {
        foreach (self::$fonts as $font) {
            if ($font->id === $fontId) {
                return true;
            }
        }

        return false;
    }

    /** Cache-Version einer Schriftdatei (ändert sich, wenn die Datei ersetzt wird). */
    public static function version(string $file): string
    {
        return substr(md5($file), 0, 8);
    }

    /** CSS-Variable eines Bereichs, z. B. --ui-font-heading. */
    public static function variable(UiFontRole $role): string
    {
        return '--ui-font-' . substr($role->value, 3);
    }

    public static function styles(): string
    {
        if (self::$fonts === []) {
            return '';
        }

        $css = '';
        $declared = [];
        foreach (self::$fonts as $font) {
            if (isset($declared[$font->id])) {
                continue;
            }
            $declared[$font->id] = true;
            foreach (self::STYLES as $style => [$weight, $fontStyle]) {
                $file = $font->files()[$style] ?? null;
                if ($file === null) {
                    continue;
                }
                $url = '/schriften/' . rawurlencode($font->id) . '/' . $style . '?v=' . self::version($file);
                $css .= "@font-face{font-family:'ui-{$font->id}';src:url('{$url}') format('truetype');font-weight:{$weight};font-style:{$fontStyle};font-display:swap}";
            }
        }

        // Bereiche ohne eigene Zuordnung erben "Text"; "Nummern" behält ohne
        // Zuordnung die Festbreitenschrift, "Text" ohne Zuordnung die Systemschrift.
        $body = self::fontFor(UiFontRole::Body);
        $root = '';
        foreach (UiFontRole::cases() as $role) {
            $font = self::fontFor($role);
            if ($font !== null) {
                $value = "'ui-{$font->id}', " . $role->fallbackStack();
            } elseif ($role === UiFontRole::Body || $role === UiFontRole::Numbers || $body === null) {
                continue;
            } else {
                $value = 'var(--ui-font-body)';
            }
            $variable = self::variable($role);
            $root .= $variable . ':' . $value . ';';
            $css .= $role->selector() . '{font-family:var(' . $variable . ')}';
        }

        return '<style id="ui-fonts">:root{' . $root . '}' . $css . '</style>';
    }
}
