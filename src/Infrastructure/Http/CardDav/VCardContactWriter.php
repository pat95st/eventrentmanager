<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\CardDav;

use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerType;
use App\Domain\Supplier\Supplier;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Baut eine einzelne vCard-Ressource (RFC 6350, Version 3.0 — breiteste
 * Client-Kompatibilität, u. a. mit älteren Adressbuch-Apps, die 4.0 noch
 * nicht unterstützen) aus einem Kunden oder Lieferanten — analog zu
 * IcsEventWriter für Vermietungen. Kunden und Lieferanten haben
 * unterschiedliche Domänenmodelle (Vor-/Nachname vs. reiner Firmenname),
 * daher zwei getrennte write*()-Methoden statt einer gemeinsamen Schnittstelle.
 */
final class VCardContactWriter
{
    public static function uidForCustomer(Customer $customer): string
    {
        return 'customer-' . $customer->id . '@materialverleih';
    }

    public static function uidForSupplier(Supplier $supplier): string
    {
        return 'supplier-' . $supplier->id . '@materialverleih';
    }

    /** ETag-Wert (inkl. umschließender Anführungszeichen, wie von RFC 6352 verlangt). */
    public static function etagForCustomer(Customer $customer): string
    {
        return '"' . sha1($customer->id . '-' . $customer->updatedAt->format(DATE_ATOM)) . '"';
    }

    public static function etagForSupplier(Supplier $supplier): string
    {
        return '"' . sha1($supplier->id . '-' . $supplier->updatedAt->format(DATE_ATOM)) . '"';
    }

    public static function writeCustomer(Customer $customer): string
    {
        $lines = self::header(self::uidForCustomer($customer), $customer->updatedAt);

        $lines[] = 'N:' . self::escape($customer->lastName) . ';' . self::escape($customer->firstName) . ';;;';
        $lines[] = 'FN:' . self::escape($customer->displayName());

        if ($customer->type === CustomerType::Geschaeftlich && $customer->companyName !== null && trim($customer->companyName) !== '') {
            $lines[] = 'ORG:' . self::escape($customer->companyName);
        }

        $lines = array_merge($lines, self::addressLines($customer->street, $customer->addressLine2, $customer->city, $customer->postalCode));
        $lines = array_merge($lines, self::contactLines($customer->phone, $customer->email));

        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines) . "\r\n";
    }

    public static function writeSupplier(Supplier $supplier): string
    {
        $lines = self::header(self::uidForSupplier($supplier), $supplier->updatedAt);

        // Lieferanten haben nur ein einzelnes Namensfeld (Firma) — N wird
        // dennoch mitgeschickt (von manchen Clients für die Sortierung
        // ausgewertet), FN und ORG tragen denselben Firmennamen.
        $lines[] = 'N:' . self::escape($supplier->name) . ';;;;';
        $lines[] = 'FN:' . self::escape($supplier->name);
        $lines[] = 'ORG:' . self::escape($supplier->name);

        $lines = array_merge($lines, self::addressLines($supplier->street, null, $supplier->city, $supplier->postalCode));
        $lines = array_merge($lines, self::contactLines($supplier->phone, $supplier->email));

        if ($supplier->customerNumber !== null && trim($supplier->customerNumber) !== '') {
            $lines[] = 'NOTE:' . self::escape('Unsere Kundennummer bei diesem Lieferanten: ' . $supplier->customerNumber);
        }

        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines) . "\r\n";
    }

    /** @return string[] */
    private static function header(string $uid, DateTimeImmutable $updatedAt): array
    {
        return [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'PRODID:-//Materialverleih//CardDAV//DE',
            'UID:' . $uid,
            'REV:' . self::utcStamp($updatedAt),
        ];
    }

    /** @return string[] */
    private static function addressLines(string $street, ?string $addressLine2, string $city, string $postalCode): array
    {
        // ADR-Komponenten (RFC 6350 §6.3.1): Postfach;Adresszusatz;Straße;Ort;Region;PLZ;Land
        $adr = implode(';', [
            '',
            self::escape($addressLine2 ?? ''),
            self::escape($street),
            self::escape($city),
            '',
            self::escape($postalCode),
            '',
        ]);

        return ['ADR;TYPE=WORK:' . $adr];
    }

    /** @return string[] */
    private static function contactLines(?string $phone, ?string $email): array
    {
        $lines = [];

        if ($phone !== null && trim($phone) !== '') {
            $lines[] = 'TEL;TYPE=WORK,VOICE:' . self::escape($phone);
        }

        if ($email !== null && trim($email) !== '') {
            $lines[] = 'EMAIL;TYPE=INTERNET:' . self::escape($email);
        }

        return $lines;
    }

    private static function utcStamp(DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /** Escaping nach RFC 6350 §3.4 (Kommas, Semikola, Backslashes, Zeilenumbrüche). */
    private static function escape(string $value): string
    {
        return str_replace(['\\', "\n", "\r", ',', ';'], ['\\\\', '\\n', '', '\\,', '\\;'], $value);
    }
}
