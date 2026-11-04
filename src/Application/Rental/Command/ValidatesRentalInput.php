<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Common\VenueResolver;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use DateTimeImmutable;

/**
 * Gemeinsame Validierung für Create-/UpdateRentalHandler, damit die Regeln
 * (insbesondere die Deckelung der Einsatztage auf den Zeitraum) nicht an
 * zwei Stellen auseinanderlaufen können.
 */
trait ValidatesRentalInput
{
    /**
     * @return array{0: array<string, string>, 1: array{
     *     customerId: string, startDate: DateTimeImmutable|null, endDate: DateTimeImmutable|null,
     *     eventName: string, eventLocation: string|null, venueId: string|null, deploymentDays: int
     * }}
     */
    private function validateRentalInput(
        CustomerRepositoryInterface $customers,
        VenueRepositoryInterface $venues,
        string $customerId,
        string $rawStartDate,
        string $rawEndDate,
        string $eventName,
        string $eventLocation,
        string $rawDeploymentDays,
    ): array {
        $errors = [];

        if ($customerId === '' || $customers->findById($customerId) === null) {
            $errors['customerId'] = 'Bitte einen gültigen Kunden auswählen.';
        }

        // Der Veranstaltungsort wird als ein einziges Freitextfeld erfasst
        // (siehe rentals/form.php, Datalist mit den gespeicherten
        // Veranstaltungsorten). Serverseitig wird der eingegebene Text bei
        // exakter (Groß-/Kleinschreibung ignorierender) Übereinstimmung mit
        // einem gespeicherten Veranstaltungsort diesem zugeordnet
        // (venueId gesetzt, eventLocation auf den kanonischen Namen
        // normalisiert); ansonsten bleibt es reiner Freitext ohne venueId.
        // Bleibt das Feld leer, werden sowohl eventLocation als auch
        // venueId null.
        [$eventLocation, $venueId] = VenueResolver::resolve($venues, $eventLocation);

        $startDate = $this->parseDate($rawStartDate);
        if ($startDate === null) {
            $errors['startDate'] = 'Bitte ein gültiges Startdatum angeben.';
        }

        $endDate = $this->parseDate($rawEndDate);
        if ($endDate === null) {
            $errors['endDate'] = 'Bitte ein gültiges Enddatum angeben.';
        }

        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            $errors['endDate'] = 'Das Enddatum kann nicht vor dem Startdatum liegen.';
        }

        $eventName = trim($eventName);
        if ($eventName === '') {
            $errors['eventName'] = 'Bitte einen Veranstaltungsnamen angeben.';
        }

        $deploymentDays = filter_var(trim($rawDeploymentDays), FILTER_VALIDATE_INT);
        $maxDeploymentDays = ($startDate !== null && $endDate !== null && $endDate >= $startDate)
            ? $startDate->diff($endDate)->days + 1
            : null;

        if ($deploymentDays === false || $deploymentDays < 1) {
            $errors['deploymentDays'] = 'Bitte eine Anzahl Einsatztage von mindestens 1 angeben.';
        } elseif ($maxDeploymentDays !== null && $deploymentDays > $maxDeploymentDays) {
            $errors['deploymentDays'] = "Die Einsatztage dürfen den Zeitraum (maximal {$maxDeploymentDays} Tage) nicht überschreiten.";
        }

        return [$errors, [
            'customerId' => $customerId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'eventName' => $eventName,
            'eventLocation' => $eventLocation,
            'venueId' => $venueId,
            'deploymentDays' => $deploymentDays !== false ? $deploymentDays : 0,
        ]];
    }

    /**
     * Regeln aus Migration 130 (Betriebsurlaub, exklusive Veranstaltungen)
     * für die fertig befüllte $rental prüfen. $requireConfirmFor: Urlaube,
     * deren Hinweis bestätigt sein muss (bei Neuanlage alle betroffenen).
     *
     * @param \App\Domain\CompanyHoliday\CompanyHoliday[] $holidays
     * @param array<string, string> $errors
     */
    private function applyScheduleRules(
        ?\App\Application\Rental\RentalScheduleRules $schedule,
        \App\Domain\Rental\Rental $rental,
        array $holidays,
        bool $needsConfirmation,
        bool $confirmed,
        array &$errors,
    ): void {
        if ($schedule === null) {
            return;
        }
        if ($holidays !== []) {
            if ($needsConfirmation && !$confirmed) {
                $errors['holiday'] = 'Die Veranstaltung fällt in den Betriebsurlaub ('
                    . implode(', ', array_map(static fn ($h) => $h->label(), $holidays))
                    . '). Bitte bestätigen, dass sie trotzdem angelegt werden soll.';
            }
            $errors += $schedule->holidayLogisticsErrors($holidays, $rental->pickupAt, $rental->returnAt);
        }
        foreach (['pickupAt' => [$rental->pickupAt, 'Abholung'], 'returnAt' => [$rental->returnAt, 'Rückgabe']] as $field => [$moment, $label]) {
            if ($moment !== null && !isset($errors[$field]) && ($inHoliday = $schedule->holidaysDuring($moment, $moment)) !== []) {
                $errors[$field] = "Die {$label} fällt in den Betriebsurlaub ({$inHoliday[0]->label()}).";
            }
        }
        $blocking = $schedule->blockingError($rental);
        if ($blocking !== null) {
            $errors['blocksOtherRentals'] = $blocking;
        }
    }

    /** Termin aus dem Formular ('Y-m-d\TH:i' bzw. 'Y-m-d'), leer = null. @param array<string, string> $errors */
    private function parseMoment(string $value, string $field, array &$errors): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($parsed !== false) {
                return $parsed;
            }
        }
        $errors[$field] = 'Bitte einen gültigen Termin angeben.';

        return null;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
