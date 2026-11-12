<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Datev;

use App\Application\Datev\DatevBookingFactory;
use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerType;
use App\Domain\Datev\DatevDebtorMode;
use App\Domain\Datev\DatevSettings;
use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\InvoiceCorrection\InvoiceCorrection;
use App\Domain\InvoiceCorrection\InvoiceCorrectionLine;
use App\Domain\InvoiceCorrection\InvoiceCorrectionStatus;
use App\Domain\InvoiceCorrection\InvoiceCorrectionType;
use App\Domain\Receipt\Receipt;
use App\Domain\Receipt\ReceiptCategory;
use App\Domain\Receipt\ReceiptStatus;
use App\Domain\Supplier\Supplier;
use App\Domain\Supplier\SupplierType;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class DatevBookingFactoryTest extends Unit
{
    private function invoice(): Invoice
    {
        $now = new DateTimeImmutable('2026-10-05');

        return new Invoice(
            id: 'inv-1',
            customerId: 'cus-1',
            eventLocation: null,
            venueId: null,
            startDate: new DateTimeImmutable('2026-10-01'),
            endDate: new DateTimeImmutable('2026-10-03'),
            invoiceNumber: 'RE-2026-0001',
            invoiceDate: $now,
            dueDate: $now->modify('+14 days'),
            buyerReference: null,
            status: InvoiceStatus::Sent,
            isSmallBusiness: false,
            vatRatePercent: '19.00',
            paidAt: null,
            note: null,
            sourceOfferId: null,
            sourceRentalId: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    private function customer(string $number = 'K-00042'): Customer
    {
        $now = new DateTimeImmutable();

        return new Customer('cus-1', $number, CustomerType::Geschaeftlich, 'Müller Events GmbH', 'Max', 'Müller', 'Hauptstr. 1', null, '12345', 'Berlin', null, null, null, $now, $now);
    }

    public function testInvoiceIsBookedOnTheDebtorAgainstTheRevenueAccountForItsVatRate(): void
    {
        $factory = new DatevBookingFactory(new DatevSettings(debtorMode: DatevDebtorMode::Individual));

        $booking = $factory->forInvoice($this->invoice(), $this->customer(), 1190.0, false, '19.00');

        $this->assertNotNull($booking);
        $this->assertSame(1190.0, $booking->amount);
        $this->assertTrue($booking->isDebit);
        $this->assertSame(10042, $booking->account);
        $this->assertSame(8400, $booking->contraAccount);
        $this->assertSame('RE-2026-0001', $booking->documentField1);
        $this->assertSame('2026-10-03', $booking->serviceDate?->format('Y-m-d'));
        $this->assertSame([], $factory->warnings());
    }

    public function testSmallBusinessAndReducedRatesUseTheirOwnRevenueAccounts(): void
    {
        $factory = new DatevBookingFactory(new DatevSettings());

        $this->assertSame(8195, $factory->forInvoice($this->invoice(), $this->customer(), 100.0, true, '19.00')?->contraAccount);
        $this->assertSame(8300, $factory->forInvoice($this->invoice(), $this->customer(), 107.0, false, '7.00')?->contraAccount);
        $this->assertSame(8100, $factory->forInvoice($this->invoice(), $this->customer(), 100.0, false, '0.00')?->contraAccount);
    }

    public function testUnknownVatRateAndUnmappableCustomerNumberProduceWarnings(): void
    {
        $factory = new DatevBookingFactory(new DatevSettings(debtorMode: DatevDebtorMode::Individual));

        $booking = $factory->forInvoice($this->invoice(), $this->customer('K-ABC'), 116.0, false, '16.00');

        $this->assertSame(10000, $booking?->account);
        $this->assertSame(8400, $booking?->contraAccount);
        $this->assertCount(2, $factory->warnings());
    }

    public function testZeroAmountInvoicesAreSkipped(): void
    {
        $factory = new DatevBookingFactory(new DatevSettings());

        $this->assertNull($factory->forInvoice($this->invoice(), $this->customer(), 0.0, false, '19.00'));
    }

    public function testCancellationIsACreditOnTheDebtor(): void
    {
        $now = new DateTimeImmutable('2026-10-20');
        $correction = new InvoiceCorrection(
            id: 'cor-1',
            invoiceId: 'inv-1',
            type: InvoiceCorrectionType::Cancellation,
            status: InvoiceCorrectionStatus::Issued,
            correctionNumber: 'RK-0001',
            correctionDate: $now,
            reason: 'Storno',
            note: null,
            isSmallBusiness: false,
            vatRatePercent: '19.00',
            requiresSettlement: false,
            settledAt: null,
            lines: [new InvoiceCorrectionLine('l1', 'cor-1', 1, 'Miete', '1', '-1000.00')],
            createdAt: $now,
            updatedAt: $now,
        );

        $booking = (new DatevBookingFactory(new DatevSettings()))->forCorrection($correction, $this->invoice(), $this->customer());

        $this->assertNotNull($booking);
        $this->assertFalse($booking->isDebit);
        $this->assertSame(1190.0, $booking->amount);
        $this->assertSame(10000, $booking->account);
        $this->assertSame(8400, $booking->contraAccount);
        $this->assertSame('RE-2026-0001', $booking->documentInfo['Zu Rechnung']);
    }

    public function testReceiptIsACreditOnTheCreditorAgainstTheCategoryExpenseAccount(): void
    {
        $now = new DateTimeImmutable('2026-10-07');
        $receipt = new Receipt('rec-1', 'sup-1', 'L-4711', '238.00', $now, ReceiptCategory::Werkzeug, ReceiptStatus::Open, null, null, 'beleg.pdf', 'x.pdf', 'application/pdf', 100, $now, $now);
        $supplier = new Supplier('sup-1', 'Werkzeug Meier', SupplierType::Kauf, 'Weg 1', '12345', 'Berlin', null, null, null, $now, $now);

        $booking = (new DatevBookingFactory(new DatevSettings(receiptTaxKey: '9')))->forReceipt($receipt, $supplier);

        $this->assertNotNull($booking);
        $this->assertFalse($booking->isDebit);
        $this->assertSame(238.0, $booking->amount);
        $this->assertSame(70000, $booking->account);
        $this->assertSame(4985, $booking->contraAccount);
        $this->assertSame('9', $booking->taxKey);
        $this->assertSame('L-4711', $booking->documentField1);
    }
}
