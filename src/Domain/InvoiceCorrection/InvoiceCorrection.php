<?php

declare(strict_types=1);

namespace App\Domain\InvoiceCorrection;

use DateTimeImmutable;

/**
 * Rechnungskorrektur bzw. Stornorechnung zu einer versendeten oder bezahlten
 * Rechnung (Nutzerwunsch, Migration 121). Die Rechnung selbst bleibt
 * unverändert, die Korrektur ist ein eigenes Dokument mit Bezug darauf.
 *
 * Steuer: Kleinunternehmer-Status und USt-Satz werden beim Anlegen von der
 * Rechnung übernommen, damit die Korrektur immer zur Rechnung passt.
 *
 * requiresSettlement: war die Rechnung beim Ausstellen schon bezahlt, muss
 * der Korrekturbetrag erstattet bzw. nachgezahlt werden (settledAt). Sonst
 * verringert/erhöht die Korrektur einfach den offenen Rechnungsbetrag.
 */
final class InvoiceCorrection
{
    /** @param InvoiceCorrectionLine[] $lines */
    public function __construct(
        public readonly string $id,
        public readonly string $invoiceId,
        public readonly InvoiceCorrectionType $type,
        public InvoiceCorrectionStatus $status,
        public ?string $correctionNumber,
        public ?DateTimeImmutable $correctionDate,
        public string $reason,
        public ?string $note,
        public bool $isSmallBusiness,
        public string $vatRatePercent,
        public bool $requiresSettlement,
        public ?DateTimeImmutable $settledAt,
        public array $lines,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        // Stornorechnung: USt als exakte Umkehrung der Rechnung und bisheriger
        // Korrekturen, damit sich in Summe genau 0,00 € ergibt (sonst Rundungscent).
        public ?string $taxAmountFixed = null,
        // Letzter E-Mail-Versand (Migration 122).
        public ?DateTimeImmutable $sentAt = null,
        public ?string $sentTo = null,
    ) {
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceCorrectionStatus::Draft;
    }

    public function netTotal(): float
    {
        $sum = 0.0;
        foreach ($this->lines as $line) {
            $sum += $line->lineTotal();
        }

        return round($sum, 2);
    }

    public function taxAmount(): float
    {
        if ($this->isSmallBusiness) {
            return 0.0;
        }

        return $this->taxAmountFixed !== null ? (float) $this->taxAmountFixed : round($this->netTotal() * (float) $this->vatRatePercent / 100, 2);
    }

    public function grossTotal(): float
    {
        return round($this->netTotal() + $this->taxAmount(), 2);
    }

    /** Erstattung/Nachzahlung noch offen? */
    public function awaitsSettlement(): bool
    {
        return $this->status === InvoiceCorrectionStatus::Issued && $this->requiresSettlement && $this->settledAt === null && abs($this->grossTotal()) >= 0.005;
    }

    /** Anzeigename, z. B. "Stornorechnung RK-0001" oder "Rechnungskorrektur (Entwurf)". */
    public function title(): string
    {
        return $this->type->label() . ' ' . ($this->correctionNumber ?? '(Entwurf)');
    }
}
