<?php

declare(strict_types=1);

namespace App\Domain\Offer;

use DateTimeImmutable;

/**
 * Ein Angebot (Kostenvoranschlag) für einen Kunden — bewusst eine EIGENE,
 * von Rental unabhängige Entität (Produktentscheidung), auch wenn Kopfdaten
 * (Kunde, Veranstaltungsort, Zeitraum) strukturell ähnlich sind. Enthält nur
 * die Kopfdaten; die Positionen stehen in OfferItem.
 */
final class Offer
{
    public function __construct(
        public readonly string $id,
        public ?string $customerId,
        public ?string $eventLocation,
        public ?string $venueId,
        public DateTimeImmutable $startDate,
        public DateTimeImmutable $endDate,
        public ?DateTimeImmutable $validUntil,
        public OfferStatus $status,
        public ?string $note,
        public ?string $convertedRentalId,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        // Zubehörpauschale + Kombirabatt, siehe Invoice für die identische
        // Begründung/Snapshot-Prinzip (App\Domain\Pricing\DocumentTotals
        // kennt die gemeinsame Berechnungsreihenfolge).
        public bool $accessoryFlatRateEnabled = true,
        public ?string $accessoryFlatRatePercent = null,
        public ?string $discountType = null,
        public ?string $discountValue = null,
    ) {
    }

    /** Maximal mögliche Einsatztage für den gewählten Zeitraum (inklusive Start- und Endtag), analog Rental::maxDeploymentDays(). */
    public function maxDeploymentDays(): int
    {
        return $this->startDate->diff($this->endDate)->days + 1;
    }

    /**
     * Anzahl Einsatztage für die Preisstaffelung (RentalPriceCalculator kennt
     * nur Stufen 1 bis 7 — bei längeren Zeiträumen wird die höchste Stufe
     * (7 Tage) als Näherung verwendet, siehe RentalPriceCalculator).
     */
    public function deploymentDaysForPricing(): int
    {
        return min(max($this->maxDeploymentDays(), 1), 7);
    }

    /**
     * Rein anzeigebezogene "abgelaufen"-Kennzeichnung: der gespeicherte
     * Status wechselt NICHT automatisch (siehe OfferStatus-Doc), aber ein
     * noch "Versendet"-es Angebot, dessen gültig-bis-Datum in der
     * Vergangenheit liegt, wird in Listen/Detailansicht zusätzlich mit
     * einem "abgelaufen"-Badge markiert.
     */
    public function isExpiredForDisplay(?DateTimeImmutable $today = null): bool
    {
        if ($this->status !== OfferStatus::Sent || $this->validUntil === null) {
            return false;
        }

        $today ??= new DateTimeImmutable('today');

        return $this->validUntil < $today;
    }

    public function isEditable(): bool
    {
        return $this->status === OfferStatus::Draft;
    }
}
