<?php

declare(strict_types=1);

namespace App\Domain\Rma;

use DateTimeImmutable;

final class Rma
{
    public function __construct(
        public readonly string $id,
        public readonly string $inventoryNumberId,
        public RmaKind $kind,
        public ?string $reason,           // Grund der Einsendung, z. B. "Defekt: kein Bild"
        public ?string $serviceProvider,  // Werkstatt / Hersteller-Service
        public ?string $rmaReference,     // RMA-/Vorgangsnummer des Dienstleisters
        public ?DateTimeImmutable $sentAt,
        public ?DateTimeImmutable $returnedAt,
        public ?string $notes,
        public ?string $workedHours,      // Arbeitszeit in Stunden, nur bei Eigenreparatur gesetzt
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public function status(): RmaStatus
    {
        return RmaStatus::compute($this->sentAt, $this->returnedAt);
    }
}
