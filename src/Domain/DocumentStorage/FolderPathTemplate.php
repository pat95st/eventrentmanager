<?php

declare(strict_types=1);

namespace App\Domain\DocumentStorage;

use App\Domain\Customer\Customer;
use App\Domain\Rental\Rental;

/**
 * Namens-/Pfadvorlagen für Veranstaltungs- und Kundenordner (je Ziellaufwerk
 * einstellbar, Migration 148). "/" in der Vorlage trennt Ordnerebenen; in den
 * eingesetzten Werten wird es dagegen ersetzt, damit z. B. eine Veranstaltung
 * "Sommerfest 24/25" keinen zusätzlichen Unterordner erzeugt.
 */
final class FolderPathTemplate
{
    public const DEFAULT_EVENT = 'Veranstaltungen/{year}/{date} {event_name}';
    public const DEFAULT_CUSTOMER = 'Kunden/{customer_number} {customer_name}';
    public const DEFAULT_EVENT_SUBFOLDER = '{date} {event_name}';

    /** Platzhalter => Erklärung, für Veranstaltungsordner. */
    public const EVENT_PLACEHOLDERS = [
        '{event_name}' => 'Name der Veranstaltung',
        '{event_location}' => 'Veranstaltungsort',
        '{date}' => 'Startdatum (JJJJ-MM-TT)',
        '{end_date}' => 'Enddatum (JJJJ-MM-TT)',
        '{year}' => 'Jahr des Startdatums',
        '{month}' => 'Monat des Startdatums (2-stellig)',
        '{day}' => 'Tag des Startdatums (2-stellig)',
        '{customer_name}' => 'Anzeigename des Kunden',
        '{customer_number}' => 'Kundennummer',
    ];

    /** Platzhalter => Erklärung, für Kundenordner. */
    public const CUSTOMER_PLACEHOLDERS = [
        '{customer_name}' => 'Anzeigename (Firma bzw. Vor- und Nachname)',
        '{customer_number}' => 'Kundennummer',
        '{company}' => 'Firmenname',
        '{first_name}' => 'Vorname',
        '{last_name}' => 'Nachname',
        '{city}' => 'Ort',
    ];

    /** @return array<string, string> */
    public static function eventValues(Rental $rental, Customer $customer): array
    {
        return [
            '{event_name}' => $rental->eventName,
            '{event_location}' => $rental->eventLocation ?? '',
            '{date}' => $rental->startDate->format('Y-m-d'),
            '{end_date}' => $rental->endDate->format('Y-m-d'),
            '{year}' => $rental->startDate->format('Y'),
            '{month}' => $rental->startDate->format('m'),
            '{day}' => $rental->startDate->format('d'),
            '{customer_name}' => $customer->displayName(),
            '{customer_number}' => $customer->customerNumber,
        ];
    }

    /** @return array<string, string> */
    public static function customerValues(Customer $customer): array
    {
        return [
            '{customer_name}' => $customer->displayName(),
            '{customer_number}' => $customer->customerNumber,
            '{company}' => $customer->companyName ?? '',
            '{first_name}' => $customer->firstName,
            '{last_name}' => $customer->lastName,
            '{city}' => $customer->city,
        ];
    }

    /**
     * Setzt die Werte ein und liefert einen sauberen relativen Pfad ohne
     * führenden/abschließenden Schrägstrich. Ebenen, die leer aufgelöst
     * werden, entfallen.
     *
     * @param array<string, string> $values Platzhalter (inkl. Klammern) => Rohwert
     */
    public static function resolve(string $template, array $values): string
    {
        $segments = [];
        foreach (explode('/', str_replace('\\', '/', $template)) as $segmentTemplate) {
            $segment = strtr($segmentTemplate, array_map(self::sanitizeValue(...), $values));
            $segment = self::sanitizeSegment($segment);
            if ($segment !== '') {
                $segments[] = $segment;
            }
        }

        return implode('/', $segments);
    }

    /**
     * Prüft eine vom Administrator eingegebene Vorlage.
     *
     * @param array<string, string> $allowedPlaceholders
     */
    public static function validationError(string $template, array $allowedPlaceholders): ?string
    {
        $template = trim($template);
        if ($template === '') {
            return 'Bitte eine Vorlage angeben.';
        }
        if (mb_strlen($template) > 500) {
            return 'Die Vorlage darf höchstens 500 Zeichen lang sein.';
        }
        preg_match_all('/\{[^{}]*\}/', $template, $matches);
        $unknown = array_diff(array_unique($matches[0]), array_keys($allowedPlaceholders));
        if ($unknown !== []) {
            return 'Unbekannte Platzhalter: ' . implode(', ', $unknown);
        }
        if (!str_contains($template, '{')) {
            return 'Die Vorlage braucht mindestens einen Platzhalter, sonst landen alle Ordner am selben Ort.';
        }
        foreach (explode('/', str_replace('\\', '/', $template)) as $segment) {
            if (trim($segment) === '..' || trim($segment) === '.') {
                return 'Die Vorlage darf keine Ebenen "." oder ".." enthalten.';
            }
        }

        return null;
    }

    /** Ein eingesetzter Wert darf weder eine neue Ordnerebene noch auf gängigen Systemen verbotene Zeichen einbringen. */
    private static function sanitizeValue(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = preg_replace('#[/\\\\:*?"<>|]+#u', '-', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private static function sanitizeSegment(string $segment): string
    {
        $segment = trim(preg_replace('/\s+/u', ' ', $segment) ?? '');
        // Windows-Clients (und damit die Sync-Clients von Nextcloud/ownCloud)
        // kommen mit Punkt/Leerzeichen am Ende eines Ordnernamens nicht zurecht.
        $segment = rtrim($segment, '. ');
        if ($segment === '.' || $segment === '..') {
            return '';
        }

        return mb_substr($segment, 0, 150);
    }
}
