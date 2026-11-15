<?php

declare(strict_types=1);

namespace App\Domain\Federation;

use DateTimeImmutable;

/**
 * Material or technician request between two partner installations
 * (migration 157). Both installations store the request with the same
 * $reference; $direction tells whether we asked (outgoing) or were asked
 * (incoming).
 *
 * $rentalId: for outgoing requests our own event the request was made for
 * (optional), for incoming requests the event created when accepting it.
 */
final class PartnerRequest
{
    /**
     * @param PartnerRequestItem[] $items
     * @param string[] $trades trade names (technician requests)
     * @param array{id: string, name: string}|null $preferredTechnician
     * @param list<array{name: string, status: string}> $technicians technicians planned by the provider
     */
    public function __construct(
        public readonly string $id,
        public readonly string $partnerId,
        public readonly PartnerRequestDirection $direction,
        public readonly PartnerRequestKind $kind,
        public readonly string $reference,
        public PartnerRequestStatus $status,
        public string $eventName,
        public DateTimeImmutable $startDate,
        public DateTimeImmutable $endDate,
        public ?string $eventLocation,
        public ?string $note,
        public ?string $contactName,
        public ?string $contactEmail,
        public array $items,
        public array $trades,
        public int $technicianCount,
        public ?array $preferredTechnician,
        public array $technicians,
        public ?float $discountPercent,
        public ?string $rentalId,
        public ?string $responseMessage,
        public ?string $syncError,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $respondedAt,
    ) {
    }

    public function isIncoming(): bool
    {
        return $this->direction === PartnerRequestDirection::Incoming;
    }

    public function totalQuantity(): int
    {
        return array_sum(array_map(static fn (PartnerRequestItem $item) => $item->quantity, $this->items));
    }

    public function deploymentDays(): int
    {
        return (int) $this->startDate->diff($this->endDate)->days + 1;
    }
}
