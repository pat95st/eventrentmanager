<?php

declare(strict_types=1);

namespace App\Domain\Cable;

use App\Domain\Article\ProtectionClass;

/**
 * Leitungstyp, z. B. „H07RN-F 5G6“ (Migration 125): Vorgabe, ob Leitungen
 * dieses Typs elektrisch geprüft werden müssen (und mit welcher
 * Schutzklasse), und welche Steckverbinder daran sinnvoll sind.
 */
final class CableType
{
    /** @param string[] $connectorIds erlaubte Steckverbindertypen */
    public function __construct(
        public readonly string $id,
        public string $name,
        public ?string $description,
        public bool $requiresElectricalInspection,
        public ?ProtectionClass $protectionClass,
        public array $connectorIds,
        public int $sortOrder = 0,
    ) {
    }

    public function allowsConnector(string $connectorId): bool
    {
        return in_array($connectorId, $this->connectorIds, true);
    }
}
