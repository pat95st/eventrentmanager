<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use DateTimeImmutable;

/**
 * Eine Zahlungsart mit Zahlungsziel (Migration 106), z. B. "Überweisung"
 * (14 Tage), "Bar bei Abholung" (0 Tage) oder "Vorkasse". Auswählbar auf
 * Rechnung, Angebot und Auftragsbestätigung; genau eine ist der Standard.
 * Das Zahlungsziel bestimmt beim Versenden einer Rechnung deren
 * Fälligkeitsdatum (siehe MarkInvoiceSentHandler).
 */
final class PaymentMethod
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public int $paymentTermDays,
        // Optionaler Hinweistext fürs Dokument, z. B. "Bitte überweisen Sie
        // den Betrag unter Angabe der Rechnungsnummer." Platzhalter siehe
        // renderNote().
        public ?string $noteText,
        public bool $isDefault,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /** Zahlungsziel als Text, z. B. "zahlbar innerhalb von 14 Tagen" bzw. "sofort fällig". */
    public function termLabel(): string
    {
        return match (true) {
            $this->paymentTermDays <= 0 => 'sofort fällig',
            $this->paymentTermDays === 1 => 'zahlbar innerhalb von 1 Tag',
            default => 'zahlbar innerhalb von ' . $this->paymentTermDays . ' Tagen',
        };
    }

    /**
     * Hinweistext mit ersetzten Platzhaltern: {tage} = Zahlungsziel in
     * Tagen, {faellig} = Fälligkeitsdatum (nur bei versendeten Rechnungen
     * bekannt, sonst "N Tage nach Rechnungsstellung").
     */
    public function renderNote(?DateTimeImmutable $dueDate = null): ?string
    {
        if ($this->noteText === null || trim($this->noteText) === '') {
            return null;
        }

        return strtr(trim($this->noteText), [
            '{tage}' => (string) $this->paymentTermDays,
            '{faellig}' => $dueDate !== null ? $dueDate->format('d.m.Y') : $this->paymentTermDays . ' Tage nach Rechnungsstellung',
        ]);
    }
}
