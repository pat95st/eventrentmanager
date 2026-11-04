<?php

declare(strict_types=1);

namespace App\Application\Rental;

use App\Domain\CompanyHoliday\CompanyHoliday;
use App\Domain\CompanyHoliday\CompanyHolidayRepositoryInterface;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalRepositoryInterface;
use DateTimeImmutable;

/**
 * Terminregeln für Veranstaltungen (Migration 130, Nutzerwunsch):
 *
 *  - Exklusive Veranstaltungen ("andere Vermietungen blockieren") sperren
 *    ihren belegten Zeitraum (Start/Ende bzw. frühere Abholung/spätere
 *    Rückgabe) für alle anderen Veranstaltungen — in beide Richtungen.
 *  - Betriebsurlaub: Fällt eine Veranstaltung in einen Betriebsurlaub, muss
 *    die Abholung vor dessen Beginn und die Rückgabe nach dessen Ende liegen.
 */
final class RentalScheduleRules
{
    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly ?CompanyHolidayRepositoryInterface $holidays = null,
    ) {
    }

    /** @return CompanyHoliday[] */
    public function holidaysDuring(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->holidays?->findOverlapping($from, $to) ?? [];
    }

    /** @return CompanyHoliday[] */
    public function allHolidays(): array
    {
        return $this->holidays?->findAll() ?? [];
    }

    /**
     * Fehler, wenn Abholung/Rückgabe nicht außerhalb der betroffenen
     * Betriebsurlaube liegen. Schlüssel: pickupAt / returnAt.
     *
     * @param CompanyHoliday[] $holidays
     * @return array<string, string>
     */
    public function holidayLogisticsErrors(array $holidays, ?DateTimeImmutable $pickupAt, ?DateTimeImmutable $returnAt): array
    {
        if ($holidays === []) {
            return [];
        }
        $firstStart = $holidays[0]->startDate;
        $lastEnd = $holidays[0]->endDate;
        foreach ($holidays as $holiday) {
            $firstStart = min($firstStart, $holiday->startDate);
            $lastEnd = max($lastEnd, $holiday->endDate);
        }
        $errors = [];
        if ($pickupAt === null) {
            $errors['pickupAt'] = 'Wegen des Betriebsurlaubs muss die Abholung spätestens am ' . $firstStart->modify('-1 day')->format('d.m.Y') . ' erfolgen – bitte einen Abholtermin angeben.';
        } elseif ($pickupAt->format('Y-m-d') >= $firstStart->format('Y-m-d')) {
            $errors['pickupAt'] = 'Die Abholung muss vor dem Betriebsurlaub liegen (spätestens am ' . $firstStart->modify('-1 day')->format('d.m.Y') . ').';
        }
        if ($returnAt === null) {
            $errors['returnAt'] = 'Wegen des Betriebsurlaubs ist die Rückgabe frühestens am ' . $lastEnd->modify('+1 day')->format('d.m.Y') . ' möglich – bitte einen Rückgabetermin angeben.';
        } elseif ($returnAt->format('Y-m-d') <= $lastEnd->format('Y-m-d')) {
            $errors['returnAt'] = 'Die Rückgabe muss nach dem Betriebsurlaub liegen (frühestens am ' . $lastEnd->modify('+1 day')->format('d.m.Y') . ').';
        }

        return $errors;
    }

    /**
     * Konflikte mit exklusiven Veranstaltungen: andere exklusive
     * Veranstaltungen im Zeitraum bzw. — wenn $candidate selbst exklusiv
     * ist — alle anderen Veranstaltungen im Zeitraum.
     *
     * @return Rental[]
     */
    public function blockingConflicts(Rental $candidate): array
    {
        $from = $candidate->occupiedFrom();
        $to = $candidate->occupiedUntil();
        // Abholung/Rückgabe anderer Veranstaltungen können über Start/Ende
        // hinausgehen: großzügig suchen, genau in PHP filtern.
        $conflicts = [];
        foreach ($this->rentals->findOverlapping($from->modify('-60 days'), $to->modify('+60 days')) as $other) {
            if ($other->id === $candidate->id) {
                continue;
            }
            if (!$candidate->blocksOtherRentals && !$other->blocksOtherRentals) {
                continue;
            }
            if ($other->occupiedFrom() <= $to && $other->occupiedUntil() >= $from) {
                $conflicts[] = $other;
            }
        }

        return $conflicts;
    }

    /**
     * Fehlermeldung zu blockingConflicts(), oder null ohne Konflikt.
     */
    public function blockingError(Rental $candidate): ?string
    {
        $conflicts = $this->blockingConflicts($candidate);
        if ($conflicts === []) {
            return null;
        }
        $names = array_map(
            static fn (Rental $r) => '„' . $r->eventName . '“ (' . $r->occupiedFrom()->format('d.m.Y') . ' – ' . $r->occupiedUntil()->format('d.m.Y') . ')',
            array_slice($conflicts, 0, 5),
        );
        $list = implode(', ', $names) . (count($conflicts) > 5 ? ' und ' . (count($conflicts) - 5) . ' weitere' : '');
        $exclusive = array_values(array_filter($conflicts, static fn (Rental $r) => $r->blocksOtherRentals));

        if ($exclusive !== [] && !$candidate->blocksOtherRentals) {
            return 'Der Zeitraum ist durch eine exklusive Veranstaltung gesperrt: ' . $list . '.';
        }

        return 'Diese Veranstaltung soll andere Vermietungen blockieren, im Zeitraum gibt es aber bereits: ' . $list . '.';
    }
}
