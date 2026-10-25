<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\OrderConfirmation\Query\ListOrderConfirmations;
use App\Application\Offer\Query\ListOffers;
use App\Application\Purchase\Query\ListPurchaseOrders;
use App\Application\Query\QueryBus;
use App\Application\Reporting\Query\GetIncomeExpenseOverview;
use App\Application\Rma\Query\ListRmas;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Dashboard\DashboardWidget;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Offer\OfferStatus;
use App\Domain\OrderConfirmation\OrderConfirmationStatus;
use App\Domain\Purchase\PurchaseOrderStatus;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Rma\RmaStatus;
use App\Domain\Supplier\SupplierRepositoryInterface;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\ReceiptInboxBadge;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\View;
use DateTimeImmutable;
use function App\Infrastructure\Http\t;

/**
 * Startseite ("/"): konfigurierbares Dashboard aus einzelnen, unabhängig
 * ein-/ausblendbaren Bereichen (siehe App\Domain\Dashboard\DashboardWidget
 * und /profil/dashboard für die Auswahl durch den Benutzer). Jeder Bereich
 * wird hier bewusst über die bereits bestehenden, fachlichen Queries/
 * Repository-Methoden der jeweiligen Module zusammengestellt (kein neuer
 * SQL-Code), analog zum Muster in anderen Controllern, die mehrere Quellen
 * für eine Seite zusammenführen (siehe z. B. InvoiceController::renderForm()).
 */
final class DashboardController
{
    /** Je Listen-Bereich höchstens so viele Einträge, der Rest wird nur noch über den Zähler sichtbar ("12 weitere, siehe Liste"). */
    private const int MAX_ITEMS_PER_WIDGET = 5;

    public function __construct(
        private readonly QueryBus $queries,
        private readonly RentalRepositoryInterface $rentals,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly CustomerRepositoryInterface $customers,
        private readonly SupplierRepositoryInterface $suppliers,
    ) {
    }

    public function index(Request $request): void
    {
        $user = CurrentUser::get();
        $widgets = $user?->enabledDashboardWidgets() ?? [];

        $sections = [];
        foreach ($widgets as $widget) {
            $sections[$widget->value] = $this->buildSection($widget);
        }

        echo View::page('dashboard/index', [
            'widgets' => $widgets,
            'sections' => $sections,
        ], activeNav: 'dashboard', title: t('dashboard.title'));
    }

    /**
     * Liefert eine für die View einheitliche Datenstruktur je Bereich:
     * ['count' => int, 'items' => array<array{label: string, sub: string, href: ?string}>, 'href' => string].
     * $count kann größer als count($items) sein (siehe MAX_ITEMS_PER_WIDGET).
     *
     * @return array{count: int, items: array<int, array{label: string, sub: string, href: ?string}>, href: string}
     */
    private function buildSection(DashboardWidget $widget): array
    {
        return match ($widget) {
            DashboardWidget::UpcomingRentals => $this->upcomingRentals(),
            DashboardWidget::OpenOffers => $this->openOffers(),
            DashboardWidget::OpenOrderConfirmations => $this->openOrderConfirmations(),
            DashboardWidget::UnpaidInvoices => $this->unpaidInvoices(),
            DashboardWidget::OverdueInvoices => $this->overdueInvoices(),
            DashboardWidget::OpenPurchaseOrders => $this->openPurchaseOrders(),
            DashboardWidget::ReceiptInbox => $this->receiptInbox(),
            DashboardWidget::OpenRma => $this->openRma(),
            DashboardWidget::IncomeExpense => $this->incomeExpense(),
        };
    }

    private function upcomingRentals(): array
    {
        $from = new DateTimeImmutable('today');
        $to = $from->modify('+14 days');
        $rentals = $this->rentals->findOverlapping($from, $to);

        $items = [];
        foreach (array_slice($rentals, 0, self::MAX_ITEMS_PER_WIDGET) as $rental) {
            $items[] = [
                'label' => $rental->eventName,
                'sub' => $rental->startDate->format('d.m.Y') . ' – ' . $rental->endDate->format('d.m.Y')
                    . ($rental->eventLocation !== null && $rental->eventLocation !== '' ? ' · ' . $rental->eventLocation : ''),
                'href' => '/veranstaltungen/' . $rental->id,
            ];
        }

        return ['count' => count($rentals), 'items' => $items, 'href' => '/veranstaltungen'];
    }

    private function openOffers(): array
    {
        $result = $this->queries->ask(new ListOffers(status: OfferStatus::Sent, page: 1, pageSize: self::MAX_ITEMS_PER_WIDGET));

        $items = [];
        foreach ($result->items as $offer) {
            $items[] = [
                'label' => $this->customerLabel($offer->customerId, $offer->eventLocation),
                'sub' => $offer->offerNumber . ' · ' . $offer->startDate->format('d.m.Y') . ' – ' . $offer->endDate->format('d.m.Y'),
                'href' => '/angebote/' . $offer->id,
            ];
        }

        return ['count' => $result->totalCount, 'items' => $items, 'href' => '/angebote?status=' . OfferStatus::Sent->value];
    }

    private function openOrderConfirmations(): array
    {
        $result = $this->queries->ask(new ListOrderConfirmations(status: OrderConfirmationStatus::Sent, page: 1, pageSize: self::MAX_ITEMS_PER_WIDGET));

        $items = [];
        foreach ($result->items as $orderConfirmation) {
            $items[] = [
                'label' => $this->customerLabel($orderConfirmation->customerId, $orderConfirmation->eventLocation),
                'sub' => $orderConfirmation->orderConfirmationNumber . ' · ' . $orderConfirmation->startDate->format('d.m.Y') . ' – ' . $orderConfirmation->endDate->format('d.m.Y'),
                'href' => '/auftragsbestaetigungen/' . $orderConfirmation->id,
            ];
        }

        return ['count' => $result->totalCount, 'items' => $items, 'href' => '/auftragsbestaetigungen?status=' . OrderConfirmationStatus::Sent->value];
    }

    private function unpaidInvoices(): array
    {
        $invoices = $this->invoices->findSentUnpaid();

        $items = [];
        foreach (array_slice($invoices, 0, self::MAX_ITEMS_PER_WIDGET) as $invoice) {
            $items[] = [
                'label' => $this->customerLabel($invoice->customerId, $invoice->eventLocation),
                'sub' => ($invoice->invoiceNumber ?? '–') . ($invoice->dueDate !== null ? ' · fällig ' . $invoice->dueDate->format('d.m.Y') : ''),
                'href' => '/rechnungen/' . $invoice->id,
            ];
        }

        return ['count' => count($invoices), 'items' => $items, 'href' => '/rechnungen?status=sent'];
    }

    private function overdueInvoices(): array
    {
        $invoices = $this->invoices->findOverdue();
        $today = new DateTimeImmutable('today');

        $items = [];
        foreach (array_slice($invoices, 0, self::MAX_ITEMS_PER_WIDGET) as $invoice) {
            $daysOverdue = $invoice->dueDate !== null ? $today->diff($invoice->dueDate)->days : null;
            $items[] = [
                'label' => $this->customerLabel($invoice->customerId, $invoice->eventLocation),
                'sub' => ($invoice->invoiceNumber ?? '–') . ($daysOverdue !== null ? ' · ' . $daysOverdue . ' Tage überfällig' : ''),
                'href' => '/rechnungen/' . $invoice->id,
            ];
        }

        return ['count' => count($invoices), 'items' => $items, 'href' => '/rechnungen?status=sent'];
    }

    private function openPurchaseOrders(): array
    {
        $result = $this->queries->ask(new ListPurchaseOrders(status: PurchaseOrderStatus::Ordered, page: 1, pageSize: self::MAX_ITEMS_PER_WIDGET));

        $items = [];
        foreach ($result->items as $purchaseOrder) {
            $supplier = $this->suppliers->findById($purchaseOrder->supplierId);
            $items[] = [
                'label' => $supplier?->name ?? 'Unbekannter Lieferant',
                'sub' => $purchaseOrder->orderedAt !== null ? 'Bestellt am ' . $purchaseOrder->orderedAt->format('d.m.Y') : '',
                'href' => '/bestellungen/' . $purchaseOrder->id,
            ];
        }

        return ['count' => $result->totalCount, 'items' => $items, 'href' => '/bestellungen?status=' . PurchaseOrderStatus::Ordered->value];
    }

    private function receiptInbox(): array
    {
        return ['count' => ReceiptInboxBadge::pendingCount(), 'items' => [], 'href' => '/belege'];
    }

    private function openRma(): array
    {
        $entries = array_values(array_filter(
            $this->queries->ask(new ListRmas()),
            static fn ($entry) => $entry->rma->status() !== RmaStatus::Abgeschlossen,
        ));

        $items = [];
        foreach (array_slice($entries, 0, self::MAX_ITEMS_PER_WIDGET) as $entry) {
            $items[] = [
                'label' => \App\Domain\Article\ArticleDisplayName::of($entry->article, $entry->manufacturer),
                'sub' => $entry->inventoryNumber->number . ' · ' . $entry->rma->status()->label(),
                'href' => '/rma',
            ];
        }

        return ['count' => count($entries), 'items' => $items, 'href' => '/rma'];
    }

    private function incomeExpense(): array
    {
        $now = new DateTimeImmutable();
        $overview = $this->queries->ask(new GetIncomeExpenseOverview((int) $now->format('Y')));
        $currentMonth = $overview->months[((int) $now->format('n')) - 1];

        $items = [[
            'label' => 'Einnahmen: ' . number_format($currentMonth->income, 2, ',', '.') . ' €',
            'sub' => 'Ausgaben: ' . number_format($currentMonth->expense, 2, ',', '.') . ' € · Saldo: ' . number_format($currentMonth->saldo(), 2, ',', '.') . ' €',
            'href' => null,
        ]];

        return ['count' => 0, 'items' => $items, 'href' => '/berichte/einnahmen-ausgaben'];
    }

    private function customerLabel(?string $customerId, ?string $fallback): string
    {
        if ($customerId !== null) {
            $customer = $this->customers->findById($customerId);
            if ($customer !== null) {
                return $customer->displayName();
            }
        }

        return $fallback !== null && $fallback !== '' ? $fallback : '–';
    }
}
