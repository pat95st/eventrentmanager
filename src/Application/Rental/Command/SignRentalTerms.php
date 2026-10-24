<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandInterface;

/**
 * Speichert die auf dem iPad geleistete Unterschrift der Vermietbedingungen
 * (siehe „Vermietbedingungen-Unterschrift & Ausweisprüfung“ in
 * architektur-und-setup.md). $signatureDataUrl ist der rohe Inhalt des
 * `<canvas>`-Signatur-Felds als Data-URL (`data:image/png;base64,...`), wie
 * er vom Browser per `toDataURL('image/png')` erzeugt wird.
 */
final class SignRentalTerms implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly string $signatureDataUrl,
    ) {
    }
}
