<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

use App\Domain\User\Permission;

/**
 * Ein einzelner, optional ein-/ausblendbarer Bereich auf der Startseite
 * (siehe App\Infrastructure\Http\Controllers\DashboardController sowie
 * /profil/dashboard für die Auswahl durch den Benutzer). Jeder Bereich
 * verlangt eines der bestehenden Rechte (siehe requiredPermission()) —
 * ohne dieses Recht wird er NIE angezeigt, unabhängig von der
 * Benutzereinstellung (siehe User::$dashboardWidgets).
 *
 * Neue Bereiche werden hier als weiterer case ergänzt und tauchen dann
 * automatisch (sofern berechtigt) als Standard an — siehe
 * User::enabledDashboardWidgets() für die "NULL = alle" Fallback-Logik.
 */
enum DashboardWidget: string
{
    case UpcomingRentals = 'upcoming_rentals';
    case OpenOffers = 'open_offers';
    case OpenOrderConfirmations = 'open_order_confirmations';
    case UnpaidInvoices = 'unpaid_invoices';
    case OverdueInvoices = 'overdue_invoices';
    case OpenPurchaseOrders = 'open_purchase_orders';
    case ReceiptInbox = 'receipt_inbox';
    case OpenRma = 'open_rma';
    case IncomeExpense = 'income_expense';

    public function label(): string
    {
        return match ($this) {
            self::UpcomingRentals => 'Anstehende Veranstaltungen',
            self::OpenOffers => 'Offene Angebote',
            self::OpenOrderConfirmations => 'Offene Auftragsbestätigungen',
            self::UnpaidInvoices => 'Offene Rechnungen',
            self::OverdueInvoices => 'Überfällige Rechnungen',
            self::OpenPurchaseOrders => 'Offene Bestellungen',
            self::ReceiptInbox => 'Beleg-Posteingang',
            self::OpenRma => 'Offene RMA-Vorgänge',
            self::IncomeExpense => 'Einnahmen & Ausgaben (dieser Monat)',
        };
    }

    /** Kurzer Erklärtext für die Auswahl unter /profil/dashboard. */
    public function description(): string
    {
        return match ($this) {
            self::UpcomingRentals => 'Veranstaltungen, deren Zeitraum in die nächsten 14 Tage fällt.',
            self::OpenOffers => 'Angebote mit Status "Versendet", die noch auf eine Antwort warten.',
            self::OpenOrderConfirmations => 'Auftragsbestätigungen mit Status "Versendet".',
            self::UnpaidInvoices => 'Versendete, noch nicht bezahlte Rechnungen.',
            self::OverdueInvoices => 'Rechnungen, deren Zahlungsziel bereits überschritten ist.',
            self::OpenPurchaseOrders => 'Bestellungen mit Status "Bestellt", die noch nicht abgeschlossen sind.',
            self::ReceiptInbox => 'Anzahl noch nicht zugeordneter Belege im Posteingang.',
            self::OpenRma => 'RMA-Vorgänge, die noch nicht als "Abgeschlossen" markiert sind.',
            self::IncomeExpense => 'Einnahmen, Ausgaben und Saldo des aktuellen Kalendermonats.',
        };
    }

    /** Das Recht, das ein Benutzer besitzen muss, damit dieser Bereich für ihn überhaupt in Frage kommt. */
    public function requiredPermission(): Permission
    {
        return match ($this) {
            self::UpcomingRentals,
            self::OpenOffers,
            self::OpenOrderConfirmations,
            self::UnpaidInvoices,
            self::OverdueInvoices => Permission::AusgabeRuecknahme,
            self::OpenPurchaseOrders,
            self::ReceiptInbox,
            self::OpenRma => Permission::Artikelverwaltung,
            self::IncomeExpense => Permission::Administration,
        };
    }

    /** Alle Bereiche in fester, sinnvoller Anzeigereihenfolge. */
    public static function all(): array
    {
        return [
            self::UpcomingRentals,
            self::OpenOffers,
            self::OpenOrderConfirmations,
            self::UnpaidInvoices,
            self::OverdueInvoices,
            self::OpenPurchaseOrders,
            self::ReceiptInbox,
            self::OpenRma,
            self::IncomeExpense,
        ];
    }
}
