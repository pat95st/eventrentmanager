<?php

declare(strict_types=1);

namespace App\Domain\Numbering;

/**
 * Admin-konfigurierbares Nummerierungsschema für EINEN Dokumenttyp (Zeile in
 * document_numbering_settings, Primärschlüssel documentType). $separator ist
 * nur beim Yearly-Schema relevant (zwischen Präfix/Jahr/Ziffernfolge), wird
 * beim Simple-Schema aber trotzdem mitgespeichert, damit ein späterer
 * Schema-Wechsel den zuletzt gewählten Wert nicht verliert.
 */
final class DocumentNumberingSettings
{
    public function __construct(
        public readonly NumberedDocumentType $documentType,
        public NumberingScheme $scheme,
        public ?string $prefix,
        public string $separator,
        public int $digitCount,
    ) {
    }
}
