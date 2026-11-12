<?php

declare(strict_types=1);

namespace App\Domain\Datev;

/**
 * Wie Ausgangsrechnungen auf Debitoren gebucht werden: alle auf ein
 * Sammelkonto ("Diverse Debitoren") oder je Kunde auf ein eigenes
 * Personenkonto, abgeleitet aus der Kundennummer (siehe
 * DatevSettings::debtorAccountFor()).
 */
enum DatevDebtorMode: string
{
    case Collective = 'collective';
    case Individual = 'individual';

    public function label(): string
    {
        return match ($this) {
            self::Collective => 'Sammelkonto für alle Kunden',
            self::Individual => 'Eigenes Debitorenkonto je Kunde (aus Kundennummer)',
        };
    }
}
