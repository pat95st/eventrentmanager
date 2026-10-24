<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

use DateTimeImmutable;

interface InvoiceRepositoryInterface
{
    public function save(Invoice $invoice): void;

    public function findById(string $id): ?Invoice;

    /**
     * @return Invoice[] sortiert nach Erstellungsdatum absteigend (neueste zuerst), höchstens $limit Zeilen ab $offset
     */
    public function search(?string $searchTerm, ?InvoiceStatus $status, int $limit, int $offset): array;

    /** Gesamtanzahl der Treffer für dieselben Filter wie search(), ohne LIMIT/OFFSET (für die Paginierung). */
    public function countSearch(?string $searchTerm, ?InvoiceStatus $status): int;

    public function delete(string $id): void;

    /** @return InvoiceItem[] */
    public function findItemsByInvoice(string $invoiceId): array;

    /**
     * Ersetzt sämtliche Positionen einer Rechnung durch die übergebene Liste
     * (analog OfferRepositoryInterface::replaceItems()).
     *
     * @param InvoiceItem[] $items
     */
    public function replaceItems(string $invoiceId, array $items): void;

    /** @return InvoiceLaborItem[] */
    public function findLaborItemsByInvoice(string $invoiceId): array;

    /**
     * Ersetzt sämtliche Arbeitszeit-Positionen einer Rechnung durch die
     * übergebene Liste (analog OfferRepositoryInterface::replaceLaborItems()).
     *
     * @param InvoiceLaborItem[] $items
     */
    public function replaceLaborItems(string $invoiceId, array $items): void;

    /**
     * Rechnungen im Status "Sent" mit Fälligkeitsdatum in der Vergangenheit
     * (due_date < heute) — Grundlage für das Mahnwesen, siehe
     * bin/send-dunning-notices.php.
     *
     * @return Invoice[]
     */
    public function findOverdue(): array;

    /**
     * Als bezahlt markierte Rechnungen mit Zahlungsdatum im angegebenen
     * Zeitraum (inklusive Grenzen) — Grundlage für die Einnahmen-/
     * Ausgabenübersicht (siehe GetIncomeExpenseOverviewHandler), die
     * bewusst nach dem Zufluss-/Abfluss-Prinzip (Zahlungsdatum) statt nach
     * Rechnungsdatum rechnet.
     *
     * @return Invoice[]
     */
    public function findPaidBetween(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
