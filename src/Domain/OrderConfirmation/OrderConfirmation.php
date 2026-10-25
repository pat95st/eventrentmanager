<?php

declare(strict_types=1);

namespace App\Domain\OrderConfirmation;

use DateTimeImmutable;

/**
 * Eine Auftragsbestätigung — vereinfachte Geschwister-Entität zu Offer
 * (siehe Migration 084 für die ausführliche Begründung des reduzierten
 * Funktionsumfangs: kein validUntil, keine Arbeitszeit-/Verkaufspositionen,
 * kein E-Mail-Workflow, nur PDF-Download). Kann frei angelegt oder aus
 * einem bestehenden Angebot erzeugt werden (siehe
 * CreateOrderConfirmationFromOfferHandler, $sourceOfferId).
 */
final class OrderConfirmation
{
    public function __construct(
        public readonly string $id,
        // Wird beim Anlegen sofort und verbindlich vergeben, siehe
        // CreateOrderConfirmationHandler + DocumentNumberSequence.
        public string $orderConfirmationNumber,
        public ?string $customerId,
        public ?string $eventLocation,
        public ?string $venueId,
        public DateTimeImmutable $startDate,
        public DateTimeImmutable $endDate,
        public OrderConfirmationStatus $status,
        public ?string $note,
        public readonly ?string $sourceOfferId,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public bool $accessoryFlatRateEnabled = true,
        public ?string $accessoryFlatRatePercent = null,
        public ?string $discountType = null,
        public ?string $discountValue = null,
        // Freitext ("Schlusstext"), erscheint in der Detailansicht und im
        // PDF-Export jeweils NACH der Positionsliste/Summenzeile — anders
        // als $note (siehe oben), das im Kopfbereich VOR der Positionsliste
        // steht. Siehe Invoice::$closingText für die identische Begründung.
        public ?string $closingText = null,
    ) {
    }

    public function isEditable(): bool
    {
        return $this->status === OrderConfirmationStatus::Draft;
    }
}
