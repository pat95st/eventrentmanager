<?php

declare(strict_types=1);

namespace App\Infrastructure\BankImport;

use App\Infrastructure\FileStorage\LocalFileStorage;

/**
 * Zweistufiger Ablauf (Upload+Abgleich -> Bestätigen/Abbrechen) mit
 * Datei-Zwischenablage statt eigener DB-Tabelle: die zwischen den beiden
 * Requests benötigten Daten (geparste Buchungen + Zuordnungsvorschläge)
 * werden als JSON-Datei unter storage/uploads/bank-import-staging/
 * {importId}.json über die bereits vorhandene LocalFileStorage abgelegt
 * (siehe Designentscheidung in der Projekt-Dokumentation). Bekannte Lücke:
 * wird ein Import weder bestätigt noch abgebrochen, bleibt die Datei
 * verwaist liegen — es gibt aktuell keinen Aufräum-Cronjob dafür.
 */
final class BankImportStaging
{
    private const SUB_DIRECTORY = 'bank-import-staging';

    public function __construct(private readonly LocalFileStorage $fileStorage)
    {
    }

    /** @param array<string, mixed> $data */
    public function store(string $importId, array $data): void
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $this->fileStorage->storeRawBytes($json, self::SUB_DIRECTORY, $importId, 'staging.json', 'application/json');
    }

    /** @return array<string, mixed>|null */
    public function load(string $importId): ?array
    {
        $path = $this->fileStorage->absolutePath(self::SUB_DIRECTORY, $importId . '.json');
        if (!is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function discard(string $importId): void
    {
        $this->fileStorage->delete(self::SUB_DIRECTORY, $importId . '.json');
    }
}
