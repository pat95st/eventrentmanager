<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Numbering\DocumentNumberingSettings;
use App\Domain\Numbering\DocumentNumberingSettingsRepositoryInterface;
use App\Domain\Numbering\NumberedDocumentType;
use App\Domain\Numbering\NumberingScheme;
use PDO;
use RuntimeException;

final class MariadbDocumentNumberingSettingsRepository implements DocumentNumberingSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByType(NumberedDocumentType $type): DocumentNumberingSettings
    {
        $stmt = $this->pdo->prepare('SELECT * FROM document_numbering_settings WHERE document_type = :document_type');
        $stmt->execute(['document_type' => $type->value]);
        $row = $stmt->fetch();

        if ($row === false) {
            throw new RuntimeException(sprintf(
                'Nummerierungseinstellungen für Dokumenttyp "%s" wurden nicht gefunden — fehlt die Migration?',
                $type->value
            ));
        }

        return $this->hydrate($row);
    }

    public function findAll(): array
    {
        $result = [];
        foreach (NumberedDocumentType::all() as $type) {
            $result[] = $this->findByType($type);
        }

        return $result;
    }

    public function save(DocumentNumberingSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE document_numbering_settings SET
                scheme = :scheme, prefix = :prefix, separator = :separator, digit_count = :digit_count
             WHERE document_type = :document_type'
        );
        $stmt->execute([
            'scheme' => $settings->scheme->value,
            'prefix' => $settings->prefix,
            'separator' => $settings->separator,
            'digit_count' => $settings->digitCount,
            'document_type' => $settings->documentType->value,
        ]);
    }

    public function getNextNumber(NumberedDocumentType $type, int $year): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT next_number FROM document_number_sequences WHERE document_type = :document_type AND sequence_year = :sequence_year'
        );
        $stmt->execute(['document_type' => $type->value, 'sequence_year' => $year]);
        $value = $stmt->fetchColumn();

        return $value === false ? 1 : (int) $value;
    }

    public function setNextNumber(NumberedDocumentType $type, int $year, int $next): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO document_number_sequences (document_type, sequence_year, next_number)
             VALUES (:document_type, :sequence_year, :next_number)
             ON DUPLICATE KEY UPDATE next_number = :next_number2'
        );
        $stmt->execute([
            'document_type' => $type->value,
            'sequence_year' => $year,
            'next_number' => $next,
            'next_number2' => $next,
        ]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): DocumentNumberingSettings
    {
        return new DocumentNumberingSettings(
            documentType: NumberedDocumentType::from($row['document_type']),
            scheme: NumberingScheme::from($row['scheme']),
            prefix: $row['prefix'],
            separator: $row['separator'],
            digitCount: (int) $row['digit_count'],
        );
    }
}
