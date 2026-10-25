<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\PdfLayout\PdfDocumentType;
use App\Domain\Settings\PdfLayout\PdfLayoutElementPosition;
use App\Domain\Settings\PdfLayout\PdfLayoutSettings;
use App\Domain\Settings\PdfLayout\PdfLayoutSettingsRepositoryInterface;
use PDO;

final class MariadbPdfLayoutSettingsRepository implements PdfLayoutSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getForDocumentType(PdfDocumentType $type): PdfLayoutSettings
    {
        $stmt = $this->pdo->prepare('SELECT * FROM pdf_layout_elements WHERE document_type = :document_type');
        $stmt->execute(['document_type' => $type->value]);

        $positions = [];
        foreach ($stmt->fetchAll() as $row) {
            $positions[$row['element_key']] = new PdfLayoutElementPosition(
                xMm: (float) $row['x_mm'],
                yMm: (float) $row['y_mm'],
                widthMm: $row['width_mm'] !== null ? (float) $row['width_mm'] : null,
                fontSize: $row['font_size'] !== null ? (float) $row['font_size'] : null,
            );
        }

        return new PdfLayoutSettings($positions);
    }

    public function saveForDocumentType(PdfDocumentType $type, array $positions): void
    {
        // Vollständiger Ersatz: zuerst alle bisherigen Zeilen dieses
        // Dokumenttyps löschen, damit ein im Editor zurückgesetzter Block
        // (nicht mehr in $positions enthalten) auch wirklich wieder auf
        // die fest kodierte Default-Logik des *PdfGenerator zurückfällt,
        // statt mit seiner alten Position in der Tabelle liegen zu
        // bleiben.
        $delete = $this->pdo->prepare('DELETE FROM pdf_layout_elements WHERE document_type = :document_type');
        $delete->execute(['document_type' => $type->value]);

        if ($positions === []) {
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO pdf_layout_elements (document_type, element_key, x_mm, y_mm, width_mm, font_size, updated_at) ' .
            'VALUES (:document_type, :element_key, :x_mm, :y_mm, :width_mm, :font_size, NOW())'
        );

        foreach ($positions as $elementKey => $position) {
            $insert->execute([
                'document_type' => $type->value,
                'element_key' => $elementKey,
                'x_mm' => $position->xMm,
                'y_mm' => $position->yMm,
                'width_mm' => $position->widthMm,
                'font_size' => $position->fontSize,
            ]);
        }
    }

    public function resetElement(PdfDocumentType $type, string $elementKey): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM pdf_layout_elements WHERE document_type = :document_type AND element_key = :element_key'
        );
        $stmt->execute(['document_type' => $type->value, 'element_key' => $elementKey]);
    }
}
