<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

use RuntimeException;

/**
 * Liest eine bewusst einfache, flache "key: value"-YAML-Datei ein
 * (Zugangsdaten-Konfiguration, z. B. database.yaml).
 *
 * Es wird absichtlich kein vollständiger YAML-Parser mitgebracht (keine
 * Composer-Abhängigkeit nötig) — unterstützt werden nur flache
 * "schluessel: wert"-Zeilen, optional in einfachen oder doppelten
 * Anführungszeichen, sowie Kommentarzeilen (#) und Leerzeilen. Das reicht
 * für eine reine Zugangsdaten-Datei völlig aus.
 *
 * Beispiel:
 *   host: 127.0.0.1
 *   port: 3306
 *   database: materialverleih
 *   username: materialverleih_app
 *   password: "ein-geheimes-passwort"
 *   charset: utf8mb4
 */
final class FlatYamlConfig
{
    /** @return array<string, string> */
    public static function load(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException(
                "Konfigurationsdatei \"{$path}\" wurde nicht gefunden. " .
                'Bitte database.example.yaml dorthin kopieren (außerhalb des Projekt-Wurzelverzeichnisses) ' .
                'und mit den echten Zugangsdaten befüllen, oder den Pfad über die Umgebungsvariable ' .
                'DATABASE_CONFIG_PATH anpassen.'
            );
        }

        if (!is_readable($path)) {
            throw new RuntimeException("Konfigurationsdatei \"{$path}\" ist nicht lesbar (Dateirechte prüfen).");
        }

        $values = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $lineNumber => $rawLine) {
            $line = trim($rawLine);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, ':')) {
                throw new RuntimeException(
                    'Ungültige Zeile ' . ($lineNumber + 1) . " in \"{$path}\": " .
                    'erwartet wird das Format "schluessel: wert".'
                );
            }

            [$key, $value] = explode(':', $line, 2);
            $key = trim($key);
            $values[$key] = self::parseScalarValue(trim($value));
        }

        return $values;
    }

    private static function parseScalarValue(string $value): string
    {
        $isDoubleQuoted = str_starts_with($value, '"') && str_ends_with($value, '"') && strlen($value) >= 2;
        $isSingleQuoted = str_starts_with($value, "'") && str_ends_with($value, "'") && strlen($value) >= 2;

        if ($isDoubleQuoted || $isSingleQuoted) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
