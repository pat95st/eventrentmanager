<?php

declare(strict_types=1);

namespace App\Domain\InvoiceCorrection;

interface InvoiceCorrectionRepositoryInterface
{
    /** Speichert Kopf und ersetzt alle Positionen. */
    public function save(InvoiceCorrection $correction): void;

    public function findById(string $id): ?InvoiceCorrection;

    /** @return InvoiceCorrection[] neueste zuerst */
    public function findByInvoice(string $invoiceId): array;

    /** @return InvoiceCorrection[] neueste zuerst, optional nach Nummer/Rechnungsnummer/Kunde gefiltert */
    public function findAll(?string $search = null): array;

    /** @return InvoiceCorrection[] ausgestellte Korrekturen, für die Einnahmenübersicht */
    public function findIssued(): array;

    public function delete(string $id): void;
}
