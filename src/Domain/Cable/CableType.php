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
        // Migration 157: cables of this type are analog multicores (sends, returns, drum, Harting).
        public bool $isAnalogMulticore = false,
    ) {
    }

    /** Effective inspection rule: analog multicores never need an electrical inspection. */
    public function requiresInspection(): bool
    {
        return $this->requiresElectricalInspection && !$this->isAnalogMulticore;
    }

    public function allowsConnector(string $connectorId): bool
    {
        return in_array($connectorId, $this->connectorIds, true);
    }
}
