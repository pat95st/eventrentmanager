<?php

declare(strict_types=1);

namespace App\Domain\AddressBook;

use App\Domain\User\Permission;

/**
 * Die zwei fest eingebauten, nicht konfigurierbaren Adressbücher des
 * CardDAV-Servers (siehe App\Infrastructure\Http\CardDav\CardDavController)
 * — analog zum festen "Veranstaltungen"-Kalender (App\Domain\Calendar\
 * CalendarKind::Event), aber noch einfacher: es gibt keine Filter und keine
 * pro-User-Zugriffsliste, da Kunden- und Lieferantendaten bereits über die
 * bestehenden Berechtigungen Kundenverwaltung/Artikelverwaltung geschützt
 * sind (siehe requiredPermission()) — wer die jeweilige Seite im Frontend
 * sehen darf, darf das Adressbuch auch per CardDAV abonnieren.
 */
enum AddressBook: string
{
    case Customers = 'kunden';
    case Suppliers = 'lieferanten';

    public function requiredPermission(): Permission
    {
        return match ($this) {
            self::Customers => Permission::Kundenverwaltung,
            self::Suppliers => Permission::Artikelverwaltung,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Customers => 'Kunden',
            self::Suppliers => 'Lieferanten',
        };
    }

    /** @return self[] Alle Adressbücher in fester Anzeigereihenfolge. */
    public static function all(): array
    {
        return [self::Customers, self::Suppliers];
    }

    public static function fromId(string $id): ?self
    {
        return self::tryFrom($id);
    }
}
