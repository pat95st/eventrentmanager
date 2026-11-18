<?php

declare(strict_types=1);

namespace App\Domain\RentalSeries;

/**
 * Areas of a rental that can be taken over into another occurrence of a
 * series. Never copied: issued inventory numbers, returns, signatures,
 * checklist/organizer tokens, offers, order confirmations and invoices.
 * Automatic reminders need no copying: they are derived from the
 * pickup/return times of each rental.
 */
enum CopyScope: string
{
    case Material = 'material';
    case Location = 'location';
    case Logistics = 'logistics';
    case Technicians = 'technicians';
    case MinuteProgram = 'minute_program';
    case PowerPlan = 'power_plan';

    public function label(): string
    {
        return match ($this) {
            self::Material => 'Geplantes Material (Artikel, Bundles, Sets)',
            self::Location => 'Veranstaltungsort und Transportnotiz',
            self::Logistics => 'Logistik (Lager, Ausgabe-/Rückgabezeit relativ zum Termin)',
            self::Technicians => 'Techniker und Gewerke (werden neu angefragt)',
            self::MinuteProgram => 'Minutenprogramm',
            self::PowerPlan => 'Stromplanung',
        };
    }

    /** @return CopyScope[] */
    public static function defaults(): array
    {
        return self::cases();
    }

    /**
     * @param string[] $values
     * @return CopyScope[]
     */
    public static function fromValues(array $values): array
    {
        $scopes = [];
        foreach ($values as $value) {
            $scope = self::tryFrom((string) $value);
            if ($scope !== null && !in_array($scope, $scopes, true)) {
                $scopes[] = $scope;
            }
        }

        return $scopes;
    }
}
