<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Invoice\InvoiceStatus;
use DateTimeImmutable;

/** Minimal in-memory invoice repository: only the header data, positions are not stored. */
final class InMemoryInvoiceRepository implements InvoiceRepositoryInterface
{
    /** @var array<string, Invoice> */
    public array $invoices = [];

    public function save(Invoice $invoice): void
    {
        $this->invoices[$invoice->id] = $invoice;
    }

    public function findById(string $id): ?Invoice
    {
        return $this->invoices[$id] ?? null;
    }

    public function findByInvoiceNumber(string $invoiceNumber): ?Invoice
    {
        foreach ($this->invoices as $invoice) {
            if ($invoice->invoiceNumber === $invoiceNumber) {
                return $invoice;
            }
        }

        return null;
    }

    public function findSentUnpaid(): array
    {
        return array_values(array_filter($this->invoices, static fn (Invoice $i): bool => $i->status === InvoiceStatus::Sent));
    }

    public function search(?string $searchTerm, ?InvoiceStatus $status, int $limit, int $offset): array
    {
        return [];
    }

    public function countSearch(?string $searchTerm, ?InvoiceStatus $status): int
    {
        return 0;
    }

    public function delete(string $id): void
    {
        unset($this->invoices[$id]);
    }

    public function findItemsByInvoice(string $invoiceId): array
    {
        return [];
    }

    public function replaceItems(string $invoiceId, array $items): void
    {
    }

    public function findLaborItemsByInvoice(string $invoiceId): array
    {
        return [];
    }

    public function replaceLaborItems(string $invoiceId, array $items): void
    {
    }

    public function findOverdue(): array
    {
        return [];
    }

    public function findDueSoon(int $leadDays): array
    {
        return [];
    }

    public function findPaidBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return [];
    }

    public function findFinalizedBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return [];
    }

    public function findFreeTextItemsByInvoice(string $invoiceId): array
    {
        return [];
    }

    public function replaceFreeTextItems(string $invoiceId, array $items): void
    {
    }

    public function findSectionTextsByInvoice(string $invoiceId): array
    {
        return [];
    }

    public function replaceSectionTexts(string $invoiceId, array $sectionTexts): void
    {
    }

    public function findNotCancelledForRevenueForecast(): array
    {
        return [];
    }

    public function findSourceOfferIds(): array
    {
        return [];
    }
}
