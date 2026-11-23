<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/** German labels of the logbook audit fields (LogbookEntry::auditValues()). */
final class LogbookLabels
{
    private const FIELDS = [
        'tripDate' => 'Datum',
        'departTime' => 'Abfahrt',
        'arriveTime' => 'Ankunft',
        'driverUserId' => 'Fahrer',
        'driverName' => 'Name des Fahrers',
        'driverPhone' => 'Telefon',
        'vehicleId' => 'Fahrzeug',
        'otherVehicleLabel' => 'Sonstiges Fahrzeug',
        'trailerId' => 'Anhänger',
        'category' => 'Art der Fahrt',
        'rentalId' => 'Veranstaltung',
        'tourStopId' => 'Tourstop',
        'purpose' => 'Zweck',
        'from' => 'Start',
        'to' => 'Ziel',
        'roundTrip' => 'Hin- und Rückfahrt',
        'distanceKm' => 'Kilometer',
        'odometerStart' => 'Km-Stand Abfahrt',
        'odometerEnd' => 'Km-Stand Ankunft',
        'notBilled' => 'Nicht abrechnen',
        'status' => 'Status',
    ];

    public static function field(string $field): string
    {
        return self::FIELDS[$field] ?? $field;
    }
}
