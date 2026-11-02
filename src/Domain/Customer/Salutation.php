<?php

declare(strict_types=1);

namespace App\Domain\Customer;

/**
 * Anrede für Kunden und Ansprechpartner (Migration 113) und daraus die
 * Briefanrede für den Starttext der Dokumente ({anrede}).
 */
enum Salutation: string
{
    case Herr = 'herr';
    case Frau = 'frau';
    case Divers = 'divers';

    public function label(): string
    {
        return match ($this) {
            self::Herr => 'Herr',
            self::Frau => 'Frau',
            self::Divers => 'Divers',
        };
    }

    public static function normalize(?string $raw): ?string
    {
        return self::tryFrom(strtolower(trim((string) $raw)))?->value;
    }

    /**
     * "Sehr geehrter Herr Mustermann" / "Sehr geehrte Frau Muster" /
     * "Guten Tag Alex Muster" (divers) / "Sehr geehrte Damen und Herren".
     */
    public static function letterSalutation(?string $salutation, ?string $firstName, ?string $lastName): string
    {
        $lastName = trim((string) $lastName);
        $fullName = trim(trim((string) $firstName) . ' ' . $lastName);

        return match (self::tryFrom((string) $salutation)) {
            self::Herr => $lastName !== '' ? 'Sehr geehrter Herr ' . $lastName : 'Sehr geehrte Damen und Herren',
            self::Frau => $lastName !== '' ? 'Sehr geehrte Frau ' . $lastName : 'Sehr geehrte Damen und Herren',
            self::Divers => $fullName !== '' ? 'Guten Tag ' . $fullName : 'Sehr geehrte Damen und Herren',
            null => 'Sehr geehrte Damen und Herren',
        };
    }

    /** Ansprechpartner haben nur ein Namensfeld: letztes Wort = Nachname. */
    public static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $last = array_pop($parts) ?? '';

        return [implode(' ', $parts), $last];
    }
}
