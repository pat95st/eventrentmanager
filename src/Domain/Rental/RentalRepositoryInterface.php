<?php

declare(strict_types=1);

namespace App\Domain\Rental;

use DateTimeImmutable;

interface RentalRepositoryInterface
{
    public function save(Rental $rental): void;

    public function findById(string $id): ?Rental;

    /**
     * @return Rental[] sortiert nach Startdatum absteigend (neueste zuerst), höchstens $limit Zeilen ab $offset
     */
    public function search(?string $searchTerm, ?string $customerId, int $limit, int $offset): array;

    /** Gesamtanzahl der Treffer für dieselben Filter wie search(), ohne LIMIT/OFFSET (für die Paginierung). */
    public function countSearch(?string $searchTerm, ?string $customerId): int;

    /**
     * Alle Vermietungen, deren Zeitraum [startDate, endDate] den angegebenen
     * Zeitraum überschneidet (für die Kalenderansicht).
     *
     * @return Rental[]
     */
    public function findOverlapping(\DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /**
     * Alle Veranstaltungen, die einem gespeicherten Veranstaltungsort zugeordnet
     * sind (für die Veranstaltungsort-Detailseite).
     *
     * @return Rental[] sortiert nach Startdatum absteigend (neueste zuerst)
     */
    public function findByVenue(string $venueId): array;

    public function delete(string $id): void;

    /**
     * Vermietungen für einen Kalender (siehe App\Domain\Calendar\Calendar) —
     * alle gesetzten Filter werden UND-verknüpft; $categoryIds/$manufacturerId
     * matchen, sobald MINDESTENS EIN Artikel der Vermietung passt,
     * $warehouseId matcht Abhol- ODER Rückgabe-Lager. Ein leeres $categoryIds
     * und ein null-Wert für die übrigen Parameter bedeutet jeweils "kein
     * Filter auf dieser Dimension" (der unfilterte Aufruf mit allen Parametern
     * leer/null liefert damit alle Vermietungen — genutzt vom nicht
     * filterbaren Veranstaltungskalender). $rangeStart/$rangeEnd grenzen
     * zusätzlich optional auf einen Zeitraum ein (für CalDAV
     * time-range-Anfragen, siehe CalDavController::report()), analog zu
     * findOverlapping().
     *
     * @param string[] $categoryIds
     * @return Rental[] sortiert nach Startdatum aufsteigend
     */
    public function searchForCalendar(
        array $categoryIds,
        ?string $manufacturerId,
        ?string $warehouseId,
        ?string $venueId,
        ?DateTimeImmutable $rangeStart,
        ?DateTimeImmutable $rangeEnd,
    ): array;

    /**
     * Vermietungen, deren Abholtermin (pickup_at) noch in der Zukunft liegt
     * und innerhalb der nächsten $leadHours Stunden ab $now fällig wird, UND
     * für die noch KEINE Abhol-Erinnerung verschickt wurde (siehe
     * App\Domain\Rental\RentalReminderRepositoryInterface) — für
     * bin/send-rental-reminders.php. Vermietungen ohne gesetzten pickup_at
     * (siehe Klassen-Doc Rental) werden nie zurückgegeben, da für sie kein
     * präziser Erinnerungszeitpunkt bekannt ist.
     *
     * @return Rental[]
     */
    public function findPickupsDueForReminder(DateTimeImmutable $now, int $leadHours): array;

    /** Exakt wie findPickupsDueForReminder(), aber für den Rückgabetermin (return_at). @return Rental[] */
    public function findReturnsDueForReminder(DateTimeImmutable $now, int $leadHours): array;

    /**
     * Exakt wie findReturnsDueForReminder() (ebenfalls return_at als
     * Bezugstermin — der Kunde soll die Checkliste rechtzeitig vor dem
     * Abbau erhalten), aber für den Versand des Kunden-Checklisten-Links
     * (RentalReminderType::CustomerChecklist). Eigener Erinnerungstyp statt
     * Wiederverwendung von 'return', damit Rückgabe-Erinnerung und
     * Checklisten-Link unabhängig voneinander/mit unterschiedlichem Vorlauf
     * verschickt werden können (siehe RentalReminderSettings).
     *
     * @return Rental[]
     */
    public function findChecklistsDueForReminder(DateTimeImmutable $now, int $leadHours): array;
}
