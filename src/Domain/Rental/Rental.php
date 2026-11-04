<?php

declare(strict_types=1);

namespace App\Domain\Rental;

use DateTimeImmutable;

/**
 * Vermietung von Material an einen Endkunden für eine Veranstaltung.
 * Enthält nur die Kopfdaten; welche Artikel (mit Menge) geplant sind, steht
 * in RentalItem, welche konkreten Inventarnummern tatsächlich ausgegeben
 * wurden (Ausgabe-Checkliste) in RentalIssuedItem.
 */
final class Rental
{
    public function __construct(
        public readonly string $id,
        public string $customerId,
        public DateTimeImmutable $startDate,
        public DateTimeImmutable $endDate,
        public string $eventName,
        public ?string $eventLocation,
        public ?string $venueId, // optionaler Verweis auf einen Stammdaten-Veranstaltungsort (siehe App\Domain\Venue\Venue)
        public int $deploymentDays, // Einsatztage; max. Anzahl Tage von startDate bis endDate (inklusiv)
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        // Transport-/Logistik-Zuordnung (siehe App\Domain\Warehouse\Warehouse).
        // Alle 5 Felder sind nullbar und defaulten auf null, damit bestehende
        // new Rental(...)-Aufrufstellen ohne Logistik-Angabe unverändert
        // bleiben können. Sind $pickupWarehouseId und $returnWarehouseId
        // identisch (oder nur eines gesetzt), handelt es sich fachlich um
        // "ein Lager für Ausgabe und Rücklieferung" — $transportNote
        // beschreibt dann den nötigen Transport davor/danach. Unterscheiden
        // sie sich, handelt es sich um getrennte Termine an unterschiedlichen
        // Lagern ($pickupAt/$returnAt).
        public ?string $pickupWarehouseId = null,
        public ?DateTimeImmutable $pickupAt = null,
        public ?string $returnWarehouseId = null,
        public ?DateTimeImmutable $returnAt = null,
        public ?string $transportNote = null,
        // Vermietbedingungen-Unterschrift (siehe SignRentalTermsHandler):
        // eine Unterschrift je Vermietung, nicht wiederverwendbar über
        // mehrere Vermietungen hinweg (siehe „Vermietbedingungen-
        // Unterschrift & Ausweisprüfung“ in architektur-und-setup.md).
        // $termsSignatureFile trägt nur den Dateinamen (ohne Pfad) der in
        // storage/uploads/rental-terms-signatures/ gespeicherten PNG-Datei.
        public ?DateTimeImmutable $termsSignedAt = null,
        public ?string $termsSignatureFile = null,
        // Migration 130: Zeitraum für andere Vermietungen sperren ("exklusiv").
        public bool $blocksOtherRentals = false,
    ) {
    }

    /** Erster belegter Tag: Startdatum oder frühere Abholung (Migration 130). */
    public function occupiedFrom(): DateTimeImmutable
    {
        $start = $this->startDate->setTime(0, 0);
        $pickup = $this->pickupAt?->setTime(0, 0);

        return $pickup !== null && $pickup < $start ? $pickup : $start;
    }

    /** Letzter belegter Tag: Enddatum oder spätere Rückgabe (Migration 130). */
    public function occupiedUntil(): DateTimeImmutable
    {
        $end = $this->endDate->setTime(0, 0);
        $return = $this->returnAt?->setTime(0, 0);

        return $return !== null && $return > $end ? $return : $end;
    }

    /** Maximal mögliche Einsatztage für den gewählten Zeitraum (inklusive Start- und Endtag). */
    public function maxDeploymentDays(): int
    {
        return $this->startDate->diff($this->endDate)->days + 1;
    }
}
