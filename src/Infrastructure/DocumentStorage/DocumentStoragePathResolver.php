<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage;

use App\Domain\DocumentStorage\DocumentType;
use DateTimeImmutable;

/**
 * Löst die Platzhalter einer Ablagepfad-Vorlage (document_storage_assignments.target_path)
 * auf: {year}, {month} (2-stellig), {day} (2-stellig), {document_type}
 * (deutsche Bezeichnung, siehe DocumentType::label()), {number}
 * (Rechnungs-/Belegnummer bzw. ID als Fallback), {filename} (finaler
 * Dateiname inkl. Endung).
 */
final class DocumentStoragePathResolver
{
    /** @return array<string, string> Standard-Platzhalter {year}/{month}/{day}/{document_type}, ohne {number}/{filename} (dokumentspezifisch, siehe DocumentStoragePusher). */
    public static function defaultPlaceholders(DocumentType $documentType, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();

        return [
            '{year}' => $now->format('Y'),
            '{month}' => $now->format('m'),
            '{day}' => $now->format('d'),
            '{document_type}' => $documentType->label(),
        ];
    }

    /**
     * Einfaches str_replace über die übergebenen Platzhalter (Schlüssel
     * bereits inkl. geschweifter Klammern, z. B. "{number}").
     *
     * @param array<string, string> $placeholders
     */
    public static function resolvePathTemplate(string $template, array $placeholders): string
    {
        $resolved = str_replace(array_keys($placeholders), array_values($placeholders), $template);

        // Doppelte Schrägstriche einsammeln, die entstehen können, wenn ein
        // Platzhalter leer aufgelöst wird (z. B. {number} bei einer noch
        // nummernlosen Entität) — ohne führenden Schrägstrich zu verlieren.
        $resolved = preg_replace('#/{2,}#', '/', $resolved) ?? $resolved;

        return ltrim($resolved, '/');
    }
}
