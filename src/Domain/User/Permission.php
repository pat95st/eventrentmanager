<?php

declare(strict_types=1);

namespace App\Domain\User;

/**
 * Die vier Rechte, die einem Benutzer beliebig kombiniert zugewiesen werden
 * können (Mehrfachauswahl, siehe User::$permissions). "Administration"
 * schließt fachlich alle übrigen Rechte ein — das wird NICHT zusätzlich in
 * der Datenbank gespeichert, sondern zentral in User::has() geprüft, damit
 * ein Administrator nicht bei jeder neuen Berechtigung explizit
 * nachgepflegt werden muss.
 */
enum Permission: string
{
    case Administration = 'administration';
    case Artikelverwaltung = 'artikelverwaltung';
    case Kundenverwaltung = 'kundenverwaltung';
    case AusgabeRuecknahme = 'ausgabe_ruecknahme';

    public function label(): string
    {
        return match ($this) {
            self::Administration => 'Administration',
            self::Artikelverwaltung => 'Artikelverwaltung',
            self::Kundenverwaltung => 'Kundenverwaltung',
            self::AusgabeRuecknahme => 'Ausgabe und Rücknahme',
        };
    }

    /** @return Permission[] Alle Rechte in fester Anzeigereihenfolge. */
    public static function all(): array
    {
        return [self::Administration, self::Artikelverwaltung, self::Kundenverwaltung, self::AusgabeRuecknahme];
    }
}
