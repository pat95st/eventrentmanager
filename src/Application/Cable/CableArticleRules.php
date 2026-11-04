<?php

declare(strict_types=1);

namespace App\Application\Cable;

use App\Domain\Cable\CableRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;

/**
 * Feste Regeln für Leitungs-Artikel (Kategorie „Leitungen“, Nutzerwunsch):
 * - Hersteller immer „Generisch“,
 * - Länge statt Abmessungen (die Länge steht in den Leitungsangaben),
 * - kein Einkaufspreis, kein Rackeinbau, keine Firmware,
 * - immer Zubehör (nicht als eigener Posten abgerechnet, kein Tagespreis)
 *   und nicht einzeln vermietbar,
 * - Elektroprüfung genau dann, wenn der Leitungstyp sie verlangt — ohne
 *   Schutzklasse.
 *
 * Die Handler wenden die Regeln serverseitig an, egal aus welchem Formular
 * die Angaben kommen.
 */
final class CableArticleRules
{
    public function __construct(
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly CableRepositoryInterface $cables,
    ) {
    }

    /**
     * Erzwungene Werte für den Create-/Update-Befehl (Parameternamen).
     *
     * @return array<string, mixed>
     */
    public function forcedValues(?CableInput $cable): array
    {
        $type = $cable?->cableTypeId !== null && $cable->cableTypeId !== '' ? $this->cables->findTypeById($cable->cableTypeId) : null;

        return [
            'manufacturerId' => $this->manufacturers->findGeneric()?->id ?? '',
            'dimensions' => null,
            'purchasePrice' => null,
            'requiresElectricalInspection' => $type?->requiresElectricalInspection ?? false,
            'protectionClass' => null,
            'isBillable' => false,
            'dailyRentalPrice' => null,
            'is19Inch' => false,
            'rackWidth' => null,
            'heightUnits' => null,
            'installDepthCm' => null,
            'hasFirmware' => false,
            'isRentableIndividually' => false,
            'isBundle' => false,
            'bundleComponents' => [],
        ];
    }

    /** Befehl mit den erzwungenen Werten neu aufbauen (gleiche Klasse). */
    public function apply(object $command): object
    {
        $values = array_merge(get_object_vars($command), $this->forcedValues($command->cable ?? null));

        return new ($command::class)(...$values);
    }
}
