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
