<?php

declare(strict_types=1);

namespace App\Domain\Numbering;

interface DocumentNumberingSettingsRepositoryInterface
{
    public function findByType(NumberedDocumentType $type): DocumentNumberingSettings;

    /** @return DocumentNumberingSettings[] Alle vier Dokumenttypen, feste Reihenfolge siehe NumberedDocumentType::all(). */
    public function findAll(): array;

    public function save(DocumentNumberingSettings $settings): void;

    /** Aktueller Zählerstand (die NÄCHSTE zu vergebende Nummer) für einen Dokumenttyp + Schlüsseljahr, nur zur Anzeige/Vorschau. 1, wenn noch keine Zeile existiert. */
    public function getNextNumber(NumberedDocumentType $type, int $year): int;

    /** Überschreibt den Zählerstand admin-seitig frei (z. B. beim Umstieg von einem anderen Tool, um dessen Nummernschema fortzusetzen). */
    public function setNextNumber(NumberedDocumentType $type, int $year, int $next): void;
}
