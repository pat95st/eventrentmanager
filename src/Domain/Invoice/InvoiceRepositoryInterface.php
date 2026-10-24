<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

use DateTimeImmutable;

interface InvoiceRepositoryInterface
{
    public function save(Invoice $invoice): void;

    public function findById(string $id): ?Invoice;

    /**
     * Exakte Suche nach der Rechnungsnummer selbst (für den
     * Onlinebanking-Import/Zahlungsabgleich). Ohne explizites COLLATE —
     * die Standard-Tabellenkollation utf8mb4_unicode_ci ist bereits
     * case-insensitiv.
     */
    public function findByInvoiceNumber(string $invoiceNumber): ?Invoice;

    /**
     * Alle versendeten, noch nicht bezahlten Rechnungen (Grundlage für den
     * automatischen Zahlungsabgleich beim Onlinebanking-Import).
     *
     * @return Invoice[]
     */
    public function findSentUnpaid(): array;

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

    /** @return InvoiceFreeTextItem[] sortiert nach sortOrder aufsteigend (orderedRows()-Konzept, siehe InvoiceDetail). */
    public function findFreeTextItemsByInvoice(string $invoiceId): array;

    /**
     * Ersetzt sämtliche Freitext-Posten einer Rechnung durch die übergebene
     * Liste (analog OfferRepositoryInterface::replaceFreeTextItems()).
     *
     * @param InvoiceFreeTextItem[] $items
     */
    public function replaceFreeTextItems(string $invoiceId, array $items): void;

    /** @return InvoiceSectionText[] sortiert nach sortOrder aufsteigend. */
    public function findSectionTextsByInvoice(string $invoiceId): array;

    /**
     * Ersetzt sämtliche Zwischentexte einer Rechnung durch die übergebene
     * Liste (analog OfferRepositoryInterface::replaceSectionTexts()).
     *
     * @param InvoiceSectionText[] $sectionTexts
     */
    public function replaceSectionTexts(string $invoiceId, array $sectionTexts): void;

    /**
     * Alle nicht stornierten Rechnungen (Draft/Sent/Paid) — Grundlage für
     * die Umsatzprognose (siehe GetRevenueForecastHandler): anders als
     * findPaidBetween() (Zufluss-/Abfluss-Prinzip nach Zahlungsdatum) zählt
     * hier jede nicht stornierte Rechnung unabhängig vom Zahlungsstatus,
     * eingeordnet nach ihrem Veranstaltungszeitraum (Invoice::$startDate).
     *
     * @return Invoice[]
     */
    public function findNotCancelledForRevenueForecast(): array;

    /**
     * IDs aller Angebote, die bereits in eine Rechnung übernommen wurden
     * (Invoice::$sourceOfferId, siehe CreateInvoiceFromOfferHandler) —
     * verhindert bei der Umsatzprognose eine Doppelzählung desselben
     * Geschäfts (einmal als offenes Angebot, einmal als daraus entstandene
     * Rechnung).
     *
     * @return string[]
     */
    public function findSourceOfferIds(): array;
}
