<?php

declare(strict_types=1);

namespace App\Domain\Api;

/**
 * Permission granted to an API token. Scopes are grouped by functional area
 * ("<area>:read" / "<area>:write"); two special scopes guard actions that
 * are irreversible or contact customers: "invoices:finalize" (assigns the
 * final invoice number / cancels an issued invoice, GoBD relevant) and
 * "email:send" (sends documents to customers on behalf of the company).
 *
 * A write scope does NOT imply its read scope; tokens can be configured
 * independently. A token flagged "read only" (ApiToken::$readOnly) is denied
 * every non-read scope regardless of what is ticked.
 */
enum ApiScope: string
{
    case CustomersRead = 'customers:read';
    case CustomersWrite = 'customers:write';
    case ArticlesRead = 'articles:read';
    case ArticlesWrite = 'articles:write';
    case MasterDataRead = 'master_data:read';
    case MasterDataWrite = 'master_data:write';
    case RentalsRead = 'rentals:read';
    case RentalsWrite = 'rentals:write';
    case SalesRead = 'sales:read';
    case SalesWrite = 'sales:write';
    case InvoicesRead = 'invoices:read';
    case InvoicesWrite = 'invoices:write';
    case InvoicesFinalize = 'invoices:finalize';
    case FinanceRead = 'finance:read';
    case FinanceWrite = 'finance:write';
    case InventoryRead = 'inventory:read';
    case InventoryWrite = 'inventory:write';
    case MinuteProgramsRead = 'minute_programs:read';
    case ReportsRead = 'reports:read';
    case EmailSend = 'email:send';

    public function isRead(): bool
    {
        return str_ends_with($this->value, ':read');
    }

    /** German label for the settings page. */
    public function label(): string
    {
        return match ($this) {
            self::CustomersRead => 'Kunden lesen',
            self::CustomersWrite => 'Kunden schreiben',
            self::ArticlesRead => 'Artikel lesen (inkl. Inventurnummern, Kategorien, Hersteller, Sets, Verfügbarkeit)',
            self::ArticlesWrite => 'Artikel schreiben',
            self::MasterDataRead => 'Stammdaten lesen (Lieferanten, Veranstaltungsorte, Lagerorte, Fahrzeuge)',
            self::MasterDataWrite => 'Stammdaten schreiben',
            self::RentalsRead => 'Veranstaltungen lesen (inkl. Positionen, Techniker, Kalender)',
            self::RentalsWrite => 'Veranstaltungen schreiben',
            self::SalesRead => 'Angebote & Auftragsbestätigungen lesen',
            self::SalesWrite => 'Angebote & Auftragsbestätigungen schreiben',
            self::InvoicesRead => 'Rechnungen lesen (inkl. Korrekturen, Mahnungen)',
            self::InvoicesWrite => 'Rechnungsentwürfe schreiben, als bezahlt markieren',
            self::InvoicesFinalize => 'Rechnungen festschreiben & stornieren (vergibt Rechnungsnummer, unumkehrbar)',
            self::FinanceRead => 'Finanzen lesen (Belege, Konten, Bestellungen)',
            self::FinanceWrite => 'Finanzen schreiben (Belege, Bestellungen)',
            self::InventoryRead => 'Lager & Technik lesen (Cases, Racks, RMA, E-Prüfungen, Verkäufe)',
            self::InventoryWrite => 'E-Prüfungen erfassen',
            self::MinuteProgramsRead => 'Minutenprogramme lesen',
            self::ReportsRead => 'Berichte lesen',
            self::EmailSend => 'E-Mails an Kunden versenden (Angebote, Rechnungen, Mahnungen, Packlisten)',
        };
    }

    /** @return list<self> */
    public static function readScopes(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $s) => $s->isRead()));
    }

    /**
     * Parses a list of scope strings, silently dropping unknown values.
     *
     * @param iterable<mixed> $values
     * @return list<self>
     */
    public static function parseList(iterable $values): array
    {
        $scopes = [];
        foreach ($values as $value) {
            $scope = is_string($value) ? self::tryFrom(trim($value)) : null;
            if ($scope !== null && !in_array($scope, $scopes, true)) {
                $scopes[] = $scope;
            }
        }
        usort($scopes, static fn (self $a, self $b) => array_search($a, self::cases(), true) <=> array_search($b, self::cases(), true));

        return $scopes;
    }
}
