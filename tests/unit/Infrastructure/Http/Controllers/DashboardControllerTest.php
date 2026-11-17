<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http\Controllers;

use App\Application\Common\PaginatedResult;
use App\Application\Offer\Query\ListOffers;
use App\Application\Offer\Query\OfferListEntry;
use App\Application\OrderConfirmation\Query\ListOrderConfirmations;
use App\Application\OrderConfirmation\Query\OrderConfirmationListEntry;
use App\Application\Purchase\Query\ListPurchaseOrders;
use App\Application\Purchase\Query\PurchaseOrderListEntry;
use App\Application\Query\QueryBus;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Dashboard\DashboardWidget;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Offer\Offer;
use App\Domain\Offer\OfferStatus;
use App\Domain\OrderConfirmation\OrderConfirmation;
use App\Domain\OrderConfirmation\OrderConfirmationStatus;
use App\Domain\Purchase\PurchaseOrder;
use App\Domain\Purchase\PurchaseOrderStatus;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Infrastructure\Http\Controllers\DashboardController;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * The list queries behind the dashboard tiles return *ListEntry wrappers,
 * not the domain objects; the tiles used to read entity properties straight
 * off the wrappers and crashed as soon as one matching record existed.
 */
final class DashboardControllerTest extends Unit
{
    public function testOpenPurchaseOrdersTileReadsListEntries(): void
    {
        $now = new DateTimeImmutable('2026-11-10');
        $order = new PurchaseOrder('po-1', 'sup-1', PurchaseOrderStatus::Ordered, $now, null, $now, $now);
        $section = $this->section(DashboardWidget::OpenPurchaseOrders, ListPurchaseOrders::class, new PurchaseOrderListEntry($order, null, 2));

        $this->assertSame(1, $section['count']);
        $this->assertSame([
            'label' => 'Unbekannter Lieferant',
            'sub' => 'Bestellt am 10.11.2026',
            'href' => '/bestellungen/po-1',
        ], $section['items'][0]);
    }

    public function testOpenOffersTileReadsListEntries(): void
    {
        $now = new DateTimeImmutable('2026-11-10');
        $offer = new Offer('of-1', 'A-1001', null, 'Stadthalle', null, null, null, null, OfferStatus::Sent, null, null, $now, $now);
        $section = $this->section(DashboardWidget::OpenOffers, ListOffers::class, new OfferListEntry($offer, null));

        $this->assertSame(['label' => 'Stadthalle', 'sub' => 'A-1001 · kein Zeitraum', 'href' => '/angebote/of-1'], $section['items'][0]);
    }

    public function testOpenOrderConfirmationsTileReadsListEntries(): void
    {
        $now = new DateTimeImmutable('2026-11-10');
        $oc = new OrderConfirmation('oc-1', 'AB-2001', null, null, null, null, null, OrderConfirmationStatus::Sent, null, null, $now, $now);
        $section = $this->section(DashboardWidget::OpenOrderConfirmations, ListOrderConfirmations::class, new OrderConfirmationListEntry($oc, null));

        $this->assertSame(['label' => '–', 'sub' => 'AB-2001 · kein Zeitraum', 'href' => '/auftragsbestaetigungen/oc-1'], $section['items'][0]);
    }

    /**
     * @param class-string<QueryInterface> $queryClass
     * @return array{count: int, items: array<int, array{label: string, sub: string, href: ?string}>, href: string}
     */
    private function section(DashboardWidget $widget, string $queryClass, object $entry): array
    {
        $queries = new QueryBus();
        $queries->register($queryClass, new class ($entry) implements QueryHandlerInterface {
            public function __construct(private readonly object $entry)
            {
            }

            public function handle(QueryInterface $query): PaginatedResult
            {
                return new PaginatedResult([$this->entry], 1, 1, 5);
            }
        });

        $controller = new DashboardController(
            $queries,
            $this->createStub(RentalRepositoryInterface::class),
            $this->createStub(InvoiceRepositoryInterface::class),
            $this->createStub(CustomerRepositoryInterface::class),
        );

        return (new \ReflectionMethod($controller, 'buildSection'))->invoke($controller, $widget);
    }
}
