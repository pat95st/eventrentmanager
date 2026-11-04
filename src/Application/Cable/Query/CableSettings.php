<?php

declare(strict_types=1);

namespace App\Application\Cable\Query;

use App\Domain\Cable\CableType;
use App\Domain\Cable\ConnectorType;

final class CableSettings
{
    /**
     * @param CableType[] $types
     * @param ConnectorType[] $connectors
     * @param array<string, int> $usage ID => Anzahl Artikel
     */
    public function __construct(
        public readonly array $types,
        public readonly array $connectors,
        public readonly array $usage,
    ) {
    }

    /** Für das Artikelformular: Leitungstyp-ID => erlaubte Steckverbinder, Elektroprüfung, Schutzklasse. */
    public function formConfig(): array
    {
        $config = [];
        foreach ($this->types as $type) {
            $config[$type->id] = [
                'name' => $type->name,
                'connectors' => array_values($type->connectorIds),
                'inspection' => $type->requiresElectricalInspection,
                'protectionClass' => $type->protectionClass?->value,
            ];
        }

        return $config;
    }
}
